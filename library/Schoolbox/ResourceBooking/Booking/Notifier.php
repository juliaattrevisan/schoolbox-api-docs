<?php
/**
 * A notifier for bookings.
 *
 * TODO: Reintroduce the router into this class, and use it to generate URLs
 * I've removed it for now because it's no longer possible to get the DI
 * container as a global variable (fair enough), and actually requiring the
 * router as part of this class would realistically require that this object and
 * its dependencies be defined in the DI container - and that should be done as
 * part of a wider effort.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;

use Aura\Router\Router;
use Monolog\Logger;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Mapper\Exception\Deleted as DeletedException;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\User;

class Notifier extends AbstractModuleNotifier
{

    /**
     * Namespace slug, as defined in msg_namespace table.
     *
     * @var string
     */
    protected $slug = NSMapper::NS_RESOURCE_BOOKING;

    /**
     * A router - injected for the purpose of generating URLs for resource
     * booking related items.
     *
     * @var Router
     */
    protected $router;


    /**
     * {@inheritdoc}
     */
    protected function buildTopic($eventLabel, $roleType)
    {
        // noop, deprecated
    }


    /**
     * @return string
     */
    protected function getSlug()
    {
        return $this->slug;
    }


    /**
     * @param TriggerBundle $bundle
     *
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        switch ($bundle->getMethod()) {
            case Mapper::METHOD_CREATE:
                $bundle->attach('created', self::IMMEDIATELY);
                break;
            case Mapper::METHOD_UPDATE:
                // Depends on the diff.
                // If status changed: trigger its related event.
                // Otherwise, trigger the update event.
                $diff = $bundle->getEvent()->getDiff();
                if (!empty($diff['status'])) {
                    list($new, $old) = $diff['status'];
                    switch ($new) {
                        case Status\Model::STATUS_APPROVED:
                            $bundle->attach('approved', self::IMMEDIATELY);
                            break;
                        case Status\Model::STATUS_PENDING:
                            $bundle->attach('pending', self::IMMEDIATELY);
                            break;
                        case Status\Model::STATUS_REJECTED:
                            $bundle->attach('rejected', self::IMMEDIATELY);
                            break;
                    }
                } else {
                    $bundle->attach('updated', self::IMMEDIATELY);
                }
                break;
            case Mapper::METHOD_DELETE:
                $bundle->attach('cancelled', self::IMMEDIATELY);
                break;
        }
    }


    /**
     * Builds a messenger for the given object, user and event.
     *
     * (Currently, I don't care about the diff between old and new versions of
     * the booking.)
     *
     * @param Model  $object
     * @param User   $user
     * @param string $event
     *
     * @return Messenger
     * @throws \InvalidArgumentException if $event type isn't recognised
     */
    protected function buildMessenger(Model $object, User $user, $event)
    {
        // Ensure that object is hydrated. Why does this happen?
        /** @var Model $object */
        try {
            $object = $this->mapper->findById($object->getId());
        } catch (DeletedException $e) {
            $object = $e->getDeletedObject();
            $event  = 'cancelled';
        }
        $messenger = new Messenger();

        // Hydrate event owner
        /** @var EventMapper $eventMapper */
        /** @var Event $calendarEvent */
        $eventMapper    = $this->mapper->getJoinMapper('event');
        $calendarEvent  = $eventMapper->findById($object->getEvent()->getId());
        $seriesId       = $calendarEvent->getSeriesId();
        if ($seriesId) {
            // Only create a notice for the next in series
            try {
                $next = $eventMapper->findNextInSeries($seriesId);
                if ($next->getId() != $calendarEvent->getId()) {
                    // Not the next in series: raise no notification
                    return $messenger;
                }
            } catch (\Exception $e) {
                return $messenger;
            }
        }

        $owner          = $eventMapper->getJoinMapper('owner')->findById($calendarEvent->getOwner()->getId());
        $key            = $this->slug . '.' . $object->getId() . '.' . $event;
        $level          = Logger::NOTICE;
        $isOwnerCreator = $owner->getId() == $calendarEvent->getAuthor()->getId();
        $startDate      = $calendarEvent->getStartDateTime();

        $resourceUrl   = sprintf(
            '/resource-booking/item/%d?%s',
            $object->getResource()->getId(),
            http_build_query(
                array(
                    'date' => $startDate->getURLDate(),
                    'view' => 'day',
                )
            )
        );
        $messageParams = array(
            'user'     => array($user->formatName(), '/search/user/' . $user->getId()),
            'resource' => array($object->getResource()->getName(), $resourceUrl),
            'date'     => array(
                $startDate->getLocal(),
                '/calendar/full/' . $startDate->getURLDate() . '/' . $owner->getId()
            ),
            'owner'    => array($owner->formatName(), '/search/user/' . $owner->getId())
        );
        switch ($event) {
            case 'created':
                $messageText = ':user booked :resource for :owner for :date';
                break;
            case 'approved':
                $messageText = ':user approved booking of :resource for :owner for :date';
                break;
            case 'rejected':
                $messageText = ':user rejected booking of :resource for :owner for :date';
                break;
            case 'pending':
                $messageText = ':user put on hold booking of :resource for :owner for :date';
                break;
            case 'cancelled':
                $messageText = ':user cancelled booking of :resource for :owner for :date';
                break;
            case 'updated':
                $messageText = ':user changed booking of :resource for :owner for :date';
                break;
            default:
                throw new \InvalidArgumentException(
                    "Event \"$event\" not one of (created, approved, rejected, pending, cancelled, updated)"
                );
        }

        // Notify the owner of the booking (usually the person who the booking is for)
        $ownerMessage = new Message(
            $messageText,
            '/portrait.php?userId=' . $user->getExternalId() . '&size={$this->iconSize}',
            $resourceUrl
        );
        $ownerMessageParams = $messageParams;
        if ($isOwnerCreator) {
            $ownerMessageParams['owner'][0] = 'you';
        }
        $messenger->targetToUser(
            $owner->getId(),
            $ownerMessage,
            $key,
            $level,
            $ownerMessageParams
        );

        // Send a message to all the moderators of the object
        $moderatorMessage = new Message(
            $messageText,
            '/portrait.php?userId=' . $user->getExternalId() . '&size={$this->iconSize}',
            $resourceUrl
        );
        foreach ($object->getResource()->getModeratorList() as $moderator) {
            $messenger->targetToUser(
                $moderator->getId(),
                $moderatorMessage,
                $key,
                $level,
                $messageParams
            );
        }

        // Broadcast to all resource booking moderators was removed in SB-6802
//        $messenger->broadcastToService(
//            UserAccess::SERVICE_RESOURCE_BOOKING,
//            0,
//            UserAccess::ACCESS_FULL,
//            $moderatorMessage,
//            $key,
//            $level,
//            $messageParams
//        );

        return $messenger;
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyStaffCreated(Model $object, User $user /*, $diff*/)
    {
        return $this->buildMessenger($object, $user, 'created');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyStaffUpdated(Model $object, User $user /*, $diff*/)
    {
        return $this->buildMessenger($object, $user, 'updated');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyStaffApproved(Model $object, User $user /*, $diff*/)
    {
        return $this->buildMessenger($object, $user, 'approved');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyStaffPending(Model $object, User $user /*, $diff*/)
    {
        return $this->buildMessenger($object, $user, 'pending');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyStaffRejected(Model $object, User $user /*, $diff*/)
    {
        return $this->buildMessenger($object, $user, 'rejected');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyStudentCreated(Model $object, User $user /*, $diff*/)
    {
        return $this->buildMessenger($object, $user, 'created');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyStudentUpdated(Model $object, User $user /*, $diff*/)
    {
        return $this->buildMessenger($object, $user, 'updated');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyStudentApproved(Model $object, User $user /*, $diff*/)
    {
        return $this->buildMessenger($object, $user, 'approved');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyStudentPending(Model $object, User $user /*, $diff*/)
    {
        return $this->buildMessenger($object, $user, 'pending');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyStudentRejected(Model $object, User $user /*, $diff*/)
    {
        return $this->buildMessenger($object, $user, 'rejected');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyParentCreated(Model $object, User $user /*, $diff*/)
    {
        return $this->buildMessenger($object, $user, 'created');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyParentUpdated(Model $object, User $user /*, $diff*/)
    {
        return $this->buildMessenger($object, $user, 'updated');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyParentApproved(Model $object, User $user /*, $diff*/)
    {
        return $this->buildMessenger($object, $user, 'approved');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyParentPending(Model $object, User $user /*, $diff*/)
    {
        return $this->buildMessenger($object, $user, 'pending');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyParentRejected(Model $object, User $user /*, $diff*/)
    {
        return $this->buildMessenger($object, $user, 'rejected');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyStaffCancelled(Model $object, User $user /*, $diff*/)
    {
        return $this->buildMessenger($object, $user, 'cancelled');
    }
}
