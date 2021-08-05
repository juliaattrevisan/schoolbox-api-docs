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
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Aura\Router\Router;
use Monolog\Logger;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Mapper\Exception\Deleted as DeletedException;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
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
     * @return Envelope
     * @throws \InvalidArgumentException if $event type isn't recognised
     */
    protected function buildEnvelope(Model $object, User $user, $event)
    {
        // Ensure that object is hydrated. Why does this happen?
        /** @var Model $object */
        try {
            $object = $this->mapper->findById($object->getId());
            $resourceMapper = $this->mapper->getJoinMapper('resource');
            $object->setResource($resourceMapper->findById($object->getResource()->getId()));
        } catch (DeletedException $e) {
            $object = $e->getDeletedObject();
            $event  = 'cancelled';
        }
        $envelope = new Envelope();

        // Hydrate event owner
        /** @var EventMapper $eventMapper */
        $eventMapper    = $this->mapper->getJoinMapper('event');

        /** @var Event $calendarEvent */
        $calendarEvent  = $eventMapper->findById($object->getEvent()->getId());

        $seriesId       = $calendarEvent->getSeriesId();
        if ($seriesId) {
            // Only create a notice for the next in series
            try {
                $next = $eventMapper->findNextInSeries($seriesId);
                if ($next->getId() != $calendarEvent->getId()) {
                    // Not the next in series: raise no notification
                    return $envelope;
                }
            } catch (\Exception $e) {
                return $envelope;
            }
        }

        /** @var User $owner */
        $owner           = $eventMapper->getJoinMapper('owner')->findById($calendarEvent->getOwner()->getId());
        $isOwnerEffector = $user->getId() == $calendarEvent->getAuthor()->getId();
        $startDate       = $calendarEvent->getStartDateTime();

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

        switch ($event) {
            case 'created':
                $messageText = ':user booked :resource';
                break;
            case 'approved':
                $messageText = ':user approved booking of :resource';
                break;
            case 'rejected':
                $messageText = ':user rejected booking of :resource';
                break;
            case 'pending':
                $messageText = ':user put on hold booking of :resource';
                break;
            case 'cancelled':
                $messageText = ':user cancelled booking of :resource';
                break;
            case 'updated':
                $messageText = ':user changed booking of :resource';
                break;
            default:
                throw new \InvalidArgumentException(
                    "Event \"$event\" not one of (created, approved, rejected, pending, cancelled, updated)"
                );
        }

        // Don't add "for owner" part if owner is creator
        if ($isOwnerEffector) {
            $messageText .= ' for :date';
        } else {
            $messageText .= ' for :owner for :date';
        }

        $bob = (new Builder())
            ->setNamespace($this->getSlug())
            ->addTopic($this->slug . '.' . $object->getId() . '.' . $event)
            ->setSeverity(Logger::NOTICE)
            ->setTitle($messageText)
            ->setIcon('/portrait.php?id=' . $user->getId() . "&size={$this->iconSize}")
            ->setHref($resourceUrl)
            ->setParam('user', new UserParam($user))
            ->setParam('resource', new Param($object->getResource()->getName(), $resourceUrl))
            ->setParam('date', new Param($startDate->getLocal(), '/calendar/' . $startDate->getURLDate() . '/' . $owner->getId()));

        // Notify the owner of the booking (usually the person who the booking is for)
        if (!$isOwnerEffector) {
            $bob->setParam('owner', new UserParam($owner, UserParam::STACKING_APPEND, 'you'));
        }
        $envelope->targetToUser($owner, $bob->build());

        // Send a message to all the moderators of the object
        foreach ($object->getResource()->getModeratorList() as $moderator) {
            if (!$isOwnerEffector) {
                $bob->setParam('owner', new UserParam($owner));
            }
            $envelope->targetToUser($moderator, $bob->build());
        }

        return $envelope;
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Envelope
     */
    public function notifyStaffCreated(Model $object, User $user /*, $diff*/)
    {
        return $this->buildEnvelope($object, $user, 'created');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Envelope
     */
    public function notifyStaffUpdated(Model $object, User $user /*, $diff*/)
    {
        return $this->buildEnvelope($object, $user, 'updated');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Envelope
     */
    public function notifyStaffApproved(Model $object, User $user /*, $diff*/)
    {
        return $this->buildEnvelope($object, $user, 'approved');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Envelope
     */
    public function notifyStaffPending(Model $object, User $user /*, $diff*/)
    {
        return $this->buildEnvelope($object, $user, 'pending');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Envelope
     */
    public function notifyStaffRejected(Model $object, User $user /*, $diff*/)
    {
        return $this->buildEnvelope($object, $user, 'rejected');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Envelope
     */
    public function notifyStudentCreated(Model $object, User $user /*, $diff*/)
    {
        return $this->buildEnvelope($object, $user, 'created');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Envelope
     */
    public function notifyStudentUpdated(Model $object, User $user /*, $diff*/)
    {
        return $this->buildEnvelope($object, $user, 'updated');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Envelope
     */
    public function notifyStudentApproved(Model $object, User $user /*, $diff*/)
    {
        return $this->buildEnvelope($object, $user, 'approved');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Envelope
     */
    public function notifyStudentPending(Model $object, User $user /*, $diff*/)
    {
        return $this->buildEnvelope($object, $user, 'pending');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Envelope
     */
    public function notifyStudentRejected(Model $object, User $user /*, $diff*/)
    {
        return $this->buildEnvelope($object, $user, 'rejected');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Envelope
     */
    public function notifyParentCreated(Model $object, User $user /*, $diff*/)
    {
        return $this->buildEnvelope($object, $user, 'created');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Envelope
     */
    public function notifyParentUpdated(Model $object, User $user /*, $diff*/)
    {
        return $this->buildEnvelope($object, $user, 'updated');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Envelope
     */
    public function notifyParentApproved(Model $object, User $user /*, $diff*/)
    {
        return $this->buildEnvelope($object, $user, 'approved');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Envelope
     */
    public function notifyParentPending(Model $object, User $user /*, $diff*/)
    {
        return $this->buildEnvelope($object, $user, 'pending');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Envelope
     */
    public function notifyParentRejected(Model $object, User $user /*, $diff*/)
    {
        return $this->buildEnvelope($object, $user, 'rejected');
    }


    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Envelope
     */
    public function notifyStaffCancelled(Model $object, User $user /*, $diff*/)
    {
        return $this->buildEnvelope($object, $user, 'cancelled');
    }
}
