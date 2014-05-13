<?php
/**
 * A notifier for bookings.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;

use Monolog\Logger;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\User;
use Schoolbox\UserAccess;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;

class Notifier extends AbstractModuleNotifier {

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
    protected function buildTopic($eventLabel, $roleType) {
        // noop, deprecated
    }

    /**
     * {@inheritdoc}
     */
    public function loadDependencies($dependencies = null) {
        /** @var ContainerInterface $di */
        global $di;
        $this->namespace = NSMapper::getInstance()->findOneBySlug('resource_booking');
        $this->router    = $di->get('router');
    }

    /**
     * @param TriggerBundle $bundle
     *
     * @return void
     */
    public function bundleTriggers($bundle) {
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
                            $bundle->attach('pending',  self::IMMEDIATELY);
                            break;
                        case Status\Model::STATUS_REJECTED:
                            $bundle->attach('rejected', self::IMMEDIATELY);
                            break;
                    }
                }
                else {
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
    protected function buildMessenger(Model $object, User $user, $event) {
        $messenger      = new Messenger();
        $owner          = $object->getEvent()->getOwner();
        $key            = 'resource_booking.'.$object->getId().'.'.$event;
        $level          = Logger::NOTICE;
        $isOwnerCreator = $owner->getId() == $user->getId();
        $startDate      = new \Alaress_Date_Format(
            $object->getEvent()->getStartDate().' '.$object->getEvent()->getStartTime()
        );
        $messageParams  = array(
            'user'     => array(
                $user->formatName(),
                '/search/user/'.$user->getId(),
            ),
            'resource' => array(
                $object->getResource()->getName(),
                $this->router->generate('resourceItem.read', array('id' => $object->getResource()->getId()))
            ),
            'date'     => array(
                '/calendar/full/'.$owner->getId().'/'.$startDate->getUsingFormat('d-m-Y'),
                $startDate->getLocal(),
            ),
        );
        if (!$isOwnerCreator) {
            $messageParams['owner'] = array($owner->formatName(), '/search/user/'.$owner->getId(),);
        }
        switch ($event) {
            case 'created':
                $messageText = $isOwnerCreator ?
                    ':user booked :resource for :date':
                    ':user booked :resource for :owner for :date';
                break;
            case 'approved':
                $messageText = $isOwnerCreator ?
                    ':user approved booking of :resource for :date':
                    ':user approved booking of :resource for :owner for :date';
                break;
            case 'rejected':
                $messageText = $isOwnerCreator ?
                    ':user rejected booking of :resource for :date':
                    ':user rejected booking of :resource for :owner for :date';
                break;
            case 'pending':
                $messageText = $isOwnerCreator ?
                    ':user put on hold booking of :resource for :date':
                    ':user put on hold booking of :resource for :owner for :date';
                break;
            case 'cancelled':
                $messageText = $isOwnerCreator ?
                    ':user cancelled booking of :resource for :date':
                    ':user cancelled booking of :resource for :owner for :date';
                break;
            case 'updated':
                $messageText = $isOwnerCreator ?
                    ':user changed booking of :resource for :date':
                    ':user changed booking of :resource for :owner for :date';
                break;
            default:
                throw new \InvalidArgumentException(
                    "Event \"$event\" not one of (created, approved, rejected, pending, cancelled, updated)"
                );
        }

        // the message to be sent
        $message = new Message(
            $messageText,
            '/portrait.php?userId='.$user->getExternalId().'&size=square32',
            $this->router->generate('resourceBooking.edit', array('id' => $object->getId()))
        );

        // Notify the owner of the booking that someone booked for them
        // Don't show the owner in the name of this message, show "you" instead
        // (because this message is targeted at the event owner)
        if (!$isOwnerCreator) {
            $messenger->targetToUser(
                $owner->getId(),
                $message,
                $key,
                $level,
                (array('owner' => 'you') + $messageParams)
            );
        }

        // Send a message to all the moderators of the object
        foreach ($object->getResource()->getModeratorList() as $moderator) {
            $messenger->targetToUser(
                $moderator->getId(),
                $message,
                $key,
                $level,
                $messageParams
            );
        }

        // Broadcast to all resource booking moderators
        $messenger->broadcastToService(
            UserAccess::SERVICE_RESOURCE_BOOKING,
            '*',
            UserAccess::ACCESS_FULL,
            $message,
            $key,
            $level,
            $messageParams
        );

        return $messenger;
    }

    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyStaffCreated(Model $object, User $user/*, $diff*/) {
        return $this->buildMessenger($object, $user, 'created');
    }

    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyStaffUpdated(Model $object, User $user/*, $diff*/) {
        return $this->buildMessenger($object, $user, 'updated');
    }

    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyStaffApproved(Model $object, User $user/*, $diff*/) {
        return $this->buildMessenger($object, $user, 'approved');
    }

    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyStaffPending(Model $object, User $user/*, $diff*/) {
        return $this->buildMessenger($object, $user, 'pending');
    }

    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyStaffRejected(Model $object, User $user/*, $diff*/) {
        return $this->buildMessenger($object, $user, 'rejected');
    }

    /**
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyStaffCancelled(Model $object, User $user/*, $diff*/) {
        return $this->buildMessenger($object, $user, 'cancelled');
    }

}
