<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar;


use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Alaress_Date_Format;
use Calendar;
use Monolog\Logger;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Role;
use Schoolbox\User;

class EventNotifier extends AbstractModuleNotifier
{

    const TRIGGER_UPCOMING = 'upcoming';
    const TRIGGER_UPDATED  = 'updated';

    // how many minutes prior to an event the notification should go out
    const UPCOMING_WINDOW = 10;


    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_CALENDAR;
    }


    /**
     * @param TriggerBundle $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        /** @var Event $event */
        $event = $bundle->getEvent()->getObject();
        $start = $event->getStartDateTime()->getUnixTimeStamp();

        // if this event is in the past, we don't want to know about it
        if ($start < time()) {
            return;
        }

        if ($bundle->getMethod() == Mapper::METHOD_CREATE) {
            // advise 10 minutes before start
            $bundle->attach(self::TRIGGER_UPCOMING, $start - (60 * self::UPCOMING_WINDOW));
        } elseif ($bundle->getMethod() == Mapper::METHOD_UPDATE) {
            // advise 10 minutes before start, and only advise updated once published
            $pubstamp = $event->getPublishDateTime() instanceof Alaress_Date_Format
                ? $event->getPublishDateTime()->getUnixTimeStamp()
                : self::IMMEDIATELY;

            if ($event->inSeries()) {
                // attach a custom trigger for handling the event recurrence
                $upcoming = array($this, 'getNextSeriesTime');

            } else {
                $upcoming = $start - (60 * self::UPCOMING_WINDOW);

            }

            $bundle->attachEach(
                array(
                    self::TRIGGER_UPCOMING => $upcoming,
                    self::TRIGGER_UPDATED  => $pubstamp
                )
            );
        }
    }


    /**
     * @param \Schoolbox\Realtime\Schedule\Event $event
     * @return int
     * @throws Mapper\Exception
     */
    public function getNextSeriesTime($event)
    {
        /** @var Event $object */
        $object   = $event->getObject();

        try {
            $next = EventMapper::getInstance()->findNextInSeries($object->getSeriesId(), $object->getStartDateTime()->addMinutes(1));
        } catch (\Schoolbox\Mapper\Exception $e) {
            // this event is not part of a series, don't try to reschedule
            return -1;
        }

        $nextTime = $next->getStartDateTime()->getUnixTimeStamp();
        $now      = Alaress_Date_Format::now()->getUnixTimeStamp();

        // returns an with the next timestamp, or negative to cancel (ie. none left)

        if ($nextTime > $now) {
            // update the event to reference the next object in the series, and return its time
            $event->setObject($next);
            return $nextTime;
        }

        return -1;
    }


    /* ASYNC METHODS */


    /**
     * @param string $trigger
     * @param string $roleType
     * @param Event $event
     * @return Builder
     */
    protected function newBuilder($trigger, $roleType, $event)
    {
        $bob = new Builder();
        $bob->setNamespace(NSMapper::NS_CALENDAR)
            ->setIcon("/images/icon.php?size={$this->iconSize}&icon=".NSMapper::NS_CALENDAR)
            // we need to stack based on series if that's possible
            ->setHref("/calendar/event/" . $event->getId())
            ->setBody($event->getDetail())
            ->addTopic("calendar.{$event->getType()->getId()}.{$event->getId()}.$trigger")
            ->addFilter('roletype', array($roleType))
            ->setSeverity(Logger::INFO);

        if ($event->getAccess() == Event::ACCESS_CAMPUS) {
            $bob->addFilter('campus', $event->getCampusCollection()->getPropertyValues('id'));
        }

        return $bob;
    }


    /**
     * @param string $roleType
     * @param Event $event
     * @param User $author
     * @return Envelope
     */
    protected function notifyUpcoming($roleType, $event, $author)
    {
        /** @var Event $event */
        $event = $this->mapper->findById($event->getId());

        $bob = $this->newBuilder(self::TRIGGER_UPCOMING, $roleType, $event)
            ->setTitle(':name is about to start')
            ->setTimestamp($event->getStartDateTime()->getUnixTimeStamp() - (60 * self::UPCOMING_WINDOW))
            ->setParam('name', new Param($event->getTitle()));

        return $this->notify($event, array($bob->build()));
    }


    /**
     * @param string $roleType
     * @param Event $event
     * @param User $effector
     * @param array $diff
     * @return Envelope
     */
    protected function notifyUpdated($roleType, $event, $effector, $diff = null)
    {

        // If the event is not yet published, the update never happened
        if($event->getPublishDateTime()->gt(Alaress_Date_Format::now())) {
            return;
        }

        /** @var Event $event */
        $event     = $this->mapper->findById($event->getId());
        $bob       = $this->newBuilder(self::TRIGGER_UPDATED, $roleType, $event);
        $instances = array();

        // title changed
        if (isset($diff['title'])) {
            $bob->setTitle(':user renamed the event :old to :new')
                ->setParams(
                    array(
                        'user' => new UserParam($effector, ParameterInterface::STACKING_REPLACE),
                        'old'  => new Param($diff['title'][1]),
                        'new'  => new Param($event->getTitle())
                    )
                );
            $instances[] = $bob->build();
            unset($diff['title']);
        }

        // location changed
        if (isset($diff['location'])) {
            $bob->setTitle(':event has moved from :old to :new')
                ->setParams(
                    array(
                        'event' => new Param($event->getTitle()),
                        'old'   => new Param($diff['location'][1]),
                        'new'   => new Param($event->getLocation())
                    )
                );
            $instances[] = $bob->build();
            unset($diff['location']);
        }

        // time changed
        if (isset($diff['start_datetime'])) {
            $bob->setTitle(':user changed the start time of :name to :time')
                ->setParams(
                    array(
                        'user' => new UserParam($effector, ParameterInterface::STACKING_REPLACE),
                        'name' => new Param($event->getTitle()),
                        'time' => new Param($event->getStartDateTime()->getShort())
                    )
                );

            $instances[] = $bob->build();
            unset($diff['start_datetime']);
        }

        // something else changed
        if (count($diff)) {
            $bob->setTitle(':user updated the event :name')
                ->setParams(
                    array(
                        'user' => new UserParam($effector, ParameterInterface::STACKING_REPLACE),
                        'name' => new Param($event->getTitle())
                    )
                );

            $instances[] = $bob->build();
        }

        return $this->notify($event, $instances);
    }


    /**
     * @param Event $event
     * @param Instance[] $instances
     * @return Envelope
     */
    protected function notify(Event $event, $instances)
    {
        $envelope = new Envelope();

        switch ($event->getAccess()) {
            case Event::ACCESS_CAMPUS:
                $collection = $event->getRoleCollection();
                if ($collection instanceof \Traversable || is_array($collection)) {
                    foreach ($collection as $role) {
                        $role = $role instanceof Relation\Role ? $role->getRoleId() : $role;
                        foreach ($instances as $instance) {
                            $envelope->broadcastToRole($role, $instance);
                        }
                    }
                }
                break;

            case Event::ACCESS_GROUP:
                foreach ($instances as $instance) {
                    if ($cat = $event->getCategory()) {
                        $envelope->broadcastToCategory($cat, UserAccess::ACCESS_READ, $instance);
                    }
                    if ($cats = $event->getCategoryCollection()) {
                        if ($cats->count()) {
                            foreach ($cats as $cat) {
                                $envelope->broadcastToCategory($cat->getCategoryId(), UserAccess::ACCESS_READ, $instance);
                            }
                        }
                    }
                }
                break;

            case Event::ACCESS_PERSONAL:
                foreach ($instances as $instance) {
                    $envelope->targetToUser($event->getOwner(), $instance);
                }
                break;

            default:
                throw new \DomainException("Event not Campus, Group or Personal");
        }

        return $envelope;
    }


    /**
     * @param Event $event
     * @param User $author
     * @return Envelope
     */
    public function notifyStudentUpcoming($event, $author)
    {
        return $this->notifyUpcoming(Role::STUDENT, $event, $author);
    }


    /**
     * @param Event $event
     * @param User $author
     * @return Envelope
     */
    public function notifyStaffUpcoming($event, $author)
    {
        return $this->notifyUpcoming(Role::STAFF, $event, $author);
    }


    /**
     * @param Event $event
     * @param User $author
     * @return Envelope
     */
    public function notifyParentUpcoming($event, $author)
    {
        return $this->notifyUpcoming(Role::PARENT, $event, $author);
    }


    /**
     * @param Event $event
     * @param User $author
     * @param array $diff
     * @return Envelope
     */
    public function notifyStudentUpdated($event, $author, $diff = null)
    {
        return $this->notifyUpdated(Role::STUDENT, $event, $author, $diff);
    }


    /**
     * @param Event $event
     * @param User $author
     * @param array $diff
     * @return Envelope
     */
    public function notifyStaffUpdated($event, $author, $diff = null)
    {
        return $this->notifyUpdated(Role::STAFF, $event, $author, $diff);
    }


    /**
     * @param Event $event
     * @param User $author
     * @param array $diff
     * @return Envelope
     */
    public function notifyParentUpdated($event, $author, $diff = null)
    {
        return $this->notifyUpdated(Role::PARENT, $event, $author, $diff);
    }

}
