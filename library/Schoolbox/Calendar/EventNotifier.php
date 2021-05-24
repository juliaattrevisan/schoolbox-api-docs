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
use Aura\Di\ContainerInterface;
use Aura\Di\Exception\ServiceNotFound;
use Monolog\Logger;
use Schoolbox\Calendar\Event\Attendance\Model as EventAttendance;
use Schoolbox\Mapper;
use Schoolbox\MapperFactory;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Calendar\Event\Attendance\Mapper as AttendanceMapper;

class EventNotifier extends AbstractModuleNotifier
{

    const TRIGGER_UPCOMING = 'upcoming';
    const TRIGGER_UPDATED  = 'updated';
    const TRIGGER_INVITED  = 'invited';

    // how many minutes prior to an event the notification should go out
    const UPCOMING_WINDOW = 10;

    private const EVENT_DETAIL_URL = '/calendar/event/%d';

    // attributes to notify when updated
    private const UPDATE_ATTR = ['title', 'detail', 'location', 'startDateTime', 'maxAttendees'];

    /**
     * @inheritdoc
     * @throws ServiceNotFound
     */
    public static function getSetters(ContainerInterface $di): array
    {
        /** @var MapperFactory $mf */
        $mf = $di->get('mapperFactory');

        return [
            'setMapper'           => $mf->getLazy(EventMapper::class),
            'setAttendanceMapper' => $mf->getLazy(AttendanceMapper::class),
        ];
    }

    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_CALENDAR;
    }

    /** @var EventMapper */
    protected $mapper;

    /** @var AttendanceMapper */
    private $attendanceMapper;

    /**
     * @param AttendanceMapper $attendanceMapper
     */
    public function setAttendanceMapper(AttendanceMapper $attendanceMapper): void
    {
        $this->attendanceMapper = $attendanceMapper;
    }

    /**
     * @param TriggerBundle $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        /** @var Event $event */
        $event = $bundle->getEvent()->getObject();
        $diff  = $bundle->getEvent()->getDiff();
        /** @var Event $previous */
        $previous = $this->buildPrevious($event, $diff);

        $method = $bundle->getMethod();

        $previousVisible = $previous && $previous->isPublished() && $previous->getEndDateTime()->isFuture();
        $eventVisible    = $event->isPublished() && $event->getEndDateTime()->isFuture();

        /**
         * Created or published
         */
        if (
            $eventVisible
            && ($method === Mapper::METHOD_CREATE || ($method === Mapper::METHOD_UPDATE && !$previousVisible))
        ) {
            // advise 10 minutes before start
            $bundle->attach(self::TRIGGER_UPCOMING, $event->getStartDateTime()->addSeconds(-60 * self::UPCOMING_WINDOW)->getUnixTimeStamp());
            if ($event->getAttendance()) {
                $bundle->attach(self::TRIGGER_INVITED, self::IMMEDIATELY);
            }
            return;
        }

        /**
         * Deleted or unpublished
         */
        if (
            $method === Mapper::METHOD_DELETE
            || ($method === Mapper::METHOD_UPDATE && $previousVisible && !$eventVisible)
        ) {
            // Detach all existing triggers, and trigger the deleted event
            // (which retracts all messages for this event already in the queue)
            $bundle->detachEach([
                self::TRIGGER_UPCOMING,
                self::TRIGGER_UPDATED,
            ]);
            $bundle->attach(self::TRIGGER_DELETED, self::IMMEDIATELY);
            return;
        }

        /**
         * Updated past event or no-change
         */
        if (
            $diff === null
            || (\count($diff) === 1 && \array_key_exists('updatedAt', $diff))
            || (!$eventVisible && !$previousVisible)
        ) {
            return;
        }

        /**
         * Updated
         */
        // information that we care
        if (count(array_intersect(array_keys($diff), self::UPDATE_ATTR)) > 0) {
            $bundle->attach(self::TRIGGER_UPDATED, self::IMMEDIATELY);
        }

        // Bundle the upcoming trigger
        if ($event->inSeries()) {
            // attach a custom trigger for handling the event recurrence
            $upcoming = [$this, 'getNextSeriesTime'];
        } else {
            $upcoming = $event->getStartDateTime()->addSeconds(-60 * self::UPCOMING_WINDOW)->getUnixTimeStamp();
        }
        $bundle->attach(self::TRIGGER_UPCOMING, $upcoming);
        $bundle->attach(self::TRIGGER_INVITED, self::IMMEDIATELY);
    }


    /**
     * @param \Schoolbox\Realtime\Schedule\Event $event
     * @return int
     */
    public function getNextSeriesTime($event)
    {
        /** @var Event $object */
        $object = $event->getObject();

        try {
            $next = $this->mapper->findNextInSeries($object->getSeriesId(), $object->getStartDateTime()->addMinutes(1));
        } catch (MapperException $e) {
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
     * @param Event  $event
     * @return Builder
     */
    protected function newBuilder(string $trigger, string $roleType, Event $event): Builder
    {
        $bob = new Builder();
        $bob->setNamespace(NSMapper::NS_CALENDAR)
            ->setIcon("/images/icon.php?size={$this->iconSize}&icon=" . NSMapper::NS_CALENDAR)
            // we need to stack based on series if that's possible
            ->setHref(sprintf(self::EVENT_DETAIL_URL, $event->getId()))
            ->setBody($event->getDetail())
            ->addTopic("calendar.{$event->getId()}.$trigger")
            ->addFilter('roletype', [$roleType])
            ->setSeverity(Logger::INFO);

        if ($event->getAccess() == Event::ACCESS_CAMPUS) {
            $bob->addFilter('campus', $event->getCampusCollection()->getPropertyValues('id'));
        }

        return $bob;
    }


    /**
     * @param string $roleType
     * @param Event  $event
     * @param User   $effector
     * @return Envelope
     */
    protected function notifyUpcoming(string $roleType, Event $event, User $effector): Envelope
    {
        // If the event is not yet published, do not notify
        if (!$event->isPublished()) {
            return new Envelope();
        }

        /** @var Event $event */
        try {
            $event = $this->mapper->findById($event->getId());
        } catch (MapperException $e) {
            return new Envelope();
        }

        $bob = $this->newBuilder(self::TRIGGER_UPCOMING, $roleType, $event)
                    ->setTitle(':name is about to start')
                    ->setTimestamp($event->getStartDateTime()->getUnixTimeStamp() - (60 * self::UPCOMING_WINDOW))
                    ->setParam('name', new Param($event->getTitle(), sprintf(self::EVENT_DETAIL_URL, $event->getId())));

        return $this->notify($event, [$bob->build()]);
    }


    /**
     * @param string      $roleType
     * @param Event       $event
     * @param User        $effector
     * @param array |null $diff
     * @return Envelope
     */
    protected function notifyUpdated(string $roleType, Event $event, User $effector, ?array $diff = null): Envelope
    {
        // If the event is not yet published, the update never happened
        if (!$event->isPublished()) {
            return new Envelope();
        }

        // If the event is published now but wasn't before:
        // the recipient didn't know there was ever an event to receive updates
        // on, so don't notify.
        if ($this->isNewlyPublished($diff)) {
            return new Envelope();
        }

        /** @var Event $event */
        try {
            $event = $this->mapper->findById($event->getId());
        } catch (MapperException $e) {
            return new Envelope();
        }
        $bob       = $this->newBuilder(self::TRIGGER_UPDATED, $roleType, $event);
        $instances = [];

        // title changed
        if (isset($diff['title'])) {
            $bob->setTitle(':user renamed the event :old to :new')
                ->setParams(
                    [
                        'user' => new UserParam($effector),
                        'old'  => new Param($diff['title'][1]),
                        'new'  => new Param($event->getTitle(), sprintf(self::EVENT_DETAIL_URL, $event->getId())),
                    ]
                );
            $instances[] = $bob->build();
            unset($diff['title']);
        }

        // location changed
        if (isset($diff['location'])) {
            $bob->setTitle(':event has moved from :old to :new')
                ->setParams(
                    [
                        'event' => new Param($event->getTitle(), sprintf(self::EVENT_DETAIL_URL, $event->getId())),
                        'old'   => new Param($diff['location'][1]),
                        'new'   => new Param($event->getLocation()),
                    ]
                );
            $instances[] = $bob->build();
            unset($diff['location']);
        }

        // time changed
        if (isset($diff['startDateTime'])) {
            $bob->setTitle(':user changed the start time of :name to :time')
                ->setParams(
                    [
                        'user' => new UserParam($effector),
                        'name' => new Param($event->getTitle(), sprintf(self::EVENT_DETAIL_URL, $event->getId())),
                        'time' => new Param($event->getStartDateTime()->getShort()),
                    ]
                );

            $instances[] = $bob->build();
            unset($diff['startDateTime']);
        }

        // something else changed
        if (count(array_intersect(array_keys($diff), self::UPDATE_ATTR))) {
            $bob->setTitle(':user updated the event :name')
                ->setParams(
                    [
                        'user' => new UserParam($effector),
                        'name' => new Param($event->getTitle(), sprintf(self::EVENT_DETAIL_URL, $event->getId())),
                    ]
                );

            $instances[] = $bob->build();
        }

        if (empty($instances)) {
            return new Envelope();
        }

        return $this->notify($event, $instances);
    }


    /**
     * @param Event      $event
     * @param Instance[] $instances
     * @return Envelope
     */
    protected function notify(Event $event, array $instances): Envelope
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
                    if ($folders = $event->getFolderCollection()) {
                        if ($folders->count()) {
                            foreach ($folders as $folder) {
                                $envelope->broadcastToCategory($folder->getId(), UserAccess::ACCESS_READ, $instance);
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

        // notify to invitees excluding declined
        $guests = $this->attendanceMapper->findAttendances($event);
        foreach ($guests as $guest) {
            if ($guest['status'] === EventAttendance::STATUS_DECLINED) {
                continue;
            }
            foreach ($instances as $instance) {
                $envelope->targetToUser($guest['id'], $instance);
            }
        }
        return $envelope;
    }


    /**
     * @param Event $event
     * @param User  $effector
     * @return Envelope
     */
    public function notifyStudentUpcoming(Event $event, User $effector): Envelope
    {
        return $this->notifyUpcoming(Role::STUDENT, $event, $effector);
    }


    /**
     * @param Event $event
     * @param User  $effector
     * @return Envelope
     */
    public function notifyStaffUpcoming(Event $event, User $effector): Envelope
    {
        return $this->notifyUpcoming(Role::STAFF, $event, $effector);
    }


    /**
     * @param Event $event
     * @param User  $effector
     * @return Envelope
     */
    public function notifyParentUpcoming(Event $event, User $effector): Envelope
    {
        return $this->notifyUpcoming(Role::PARENT, $event, $effector);
    }


    /**
     * @param Event      $event
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentUpdated(Event $event, User $effector, ?array $diff = null): Envelope
    {
        return $this->notifyUpdated(Role::STUDENT, $event, $effector, $diff);
    }


    /**
     * @param Event      $event
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStaffUpdated(Event $event, User $effector, ?array $diff = null): Envelope
    {
        return $this->notifyUpdated(Role::STAFF, $event, $effector, $diff);
    }


    /**
     * @param Event      $event
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentUpdated(Event $event, User $effector, ?array $diff = null): Envelope
    {
        return $this->notifyUpdated(Role::PARENT, $event, $effector, $diff);
    }

    /**
     * @param string     $roleType
     * @param Event      $event
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    protected function notifyInvited(string $roleType, Event $event, User $effector, ?array $diff = null): Envelope
    {
        $envelope = new Envelope();

        if (null === $diff) {
            $newlyInvited = array_column($this->attendanceMapper->findAttendances($event), 'id');
        } elseif (isset($diff['guests'])) {
            $newlyInvited = array_diff($diff['guests'][0] ?? [], $diff['guests'][1] ?? []);
        }

        if (empty($newlyInvited)) {
            return $envelope;
        }

        /** @var Event $event */
        try {
            $event = $this->mapper->findById($event->getId());
        } catch (MapperException $e) {
            return $envelope;
        }

        $bob = $this->newBuilder(self::TRIGGER_INVITED, $roleType, $event)
                    ->setTitle(':user invited you to :name')
                    ->setTimestamp($event->getStartDateTime()->getUnixTimeStamp() - (60 * self::UPCOMING_WINDOW))
                    ->setParams(
                        [
                            'user' => new UserParam($effector),
                            'name' => new Param($event->getTitle(), sprintf(self::EVENT_DETAIL_URL, $event->getId())),
                        ]
                    );

        foreach ($newlyInvited as $guest) {
            $envelope->targetToUser($guest, $bob->build());
        }

        return $envelope;
    }

    /**
     * @param Event      $event
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentInvited(Event $event, User $effector, ?array $diff = null): Envelope
    {
        return $this->notifyInvited(Role::STUDENT, $event, $effector, $diff);
    }


    /**
     * @param Event      $event
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStaffInvited(Event $event, User $effector, ?array $diff = null): Envelope
    {
        return $this->notifyInvited(Role::STAFF, $event, $effector, $diff);
    }


    /**
     * @param Event      $event
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentInvited(Event $event, User $effector, ?array $diff = null): Envelope
    {
        return $this->notifyInvited(Role::PARENT, $event, $effector, $diff);
    }

    /**
     * On deletion of an event, retract all notifications relating to it.
     *
     * @param Event $event
     * @return Envelope
     */
    public function notifySystemDeleted(Event $event): Envelope
    {
        return $this->retractEventObject(new Envelope(), $event);
    }


    /**
     * Has the event gone from not published to published?
     *
     * @param array|null $diff
     * @return bool
     */
    private function isNewlyPublished(?array $diff): bool
    {
        $publishDiff = $diff['publishDateTime'] ?? null;
        if (!isset($publishDiff)) {
            return false;
        }
        $now = Alaress_Date_Format::now();
        [$newPublish, $oldPublish] = $diff['publishDateTime'];

        // New publish date is null: event is published as of now
        if (isset($newPublish)) {
            $newPublish = Alaress_Date_Format::newDatabaseDate($newPublish);
        } else {
            $newPublish = $now;
        }

        // Old publish date is null: event was published, and thus not newly published
        if (isset($oldPublish)) {
            $oldPublish = Alaress_Date_Format::newDatabaseDate($oldPublish);
        } else {
            return false;
        }

        return $oldPublish->gt($now) && $newPublish->lte($now);
    }
}
