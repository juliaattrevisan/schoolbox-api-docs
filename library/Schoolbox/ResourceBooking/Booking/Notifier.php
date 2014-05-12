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

class Notifier extends AbstractModuleNotifier {

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
        $this->namespace = NSMapper::getInstance()->findOneBySlug('resource_booking');
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
//            case Mapper::METHOD_UPDATE:
//                // Depends on the diff.
//                // If only the status changed: trigger an event for that.
//                // Otherwise, trigger the update event.
//                $diff = $bundle->getEvent()->getDiff();
//                if (isset($diff['status'])) {
//                    list($new, $old) = $diff['status'];
//                    switch ($new) {
//                        case Status\Model::STATUS_APPROVED:
//                            $bundle->attach('approved', self::IMMEDIATELY);
//                            break;
//                        case Status\Model::STATUS_REJECTED:
//                            $bundle->attach('rejected', self::IMMEDIATELY);
//                            break;
//                    }
//                    unset($diff['status']);
//                }
//                if (!empty($diff)) {
//                    $bundle->attach('updated', self::IMMEDIATELY);
//                }
//                break;
//            case Mapper::METHOD_DELETE:
//                $bundle->attach('cancelled', self::IMMEDIATELY);
//                break;
        }
    }

    /**
     * Notification for staff that a booking has been created.
     *
     * @param Model $object
     * @param User  $user
     *
     * @return Messenger
     */
    public function notifyStaffCreated(Model $object, User $user/*, $diff*/) {
        $messenger = new Messenger();

        // Message: $user booked booking for someone other than themselves
        $owner = $object->getEvent()->getOwner();
        $key   = 'resource_booking.'.$object->getId().'.created';
        $level = Logger::NOTICE;

        // Create message and params
        if ($owner->getId() != $user->getId()) {
            $message = new Message(
                ':user booked :resource for :owner for :date',
                '/portrait.php?userId='.$user->getExternalId().'&size=square32',
                '/resource-booking/item/'.$object->getResource()->getId()
            );
            $params = array(
                'user'     => $user->formatName(),
                'resource' => $object->getResource()->getName(),
                'date'     => $object->getEvent()->getStartDate(),
                'owner'    => $owner->formatName(),
            );

            // Notify the owner of the booking that someone booked for them
            $messenger->targetToUser(
                $owner->getId(),
                $message,
                $key,
                $level,
                (array('owner' => 'you') + $params)
            );
        }
        else {
            $message = new Message(
                ':user booked :resource for :date',
                '/portrait.php?userId='.$user->getExternalId().'&size=square32',
                '/resource-booking/item/'.$object->getResource()->getId()
            );
            $params = array(
                'user'     => $user->formatName(),
                'resource' => $object->getResource()->getName(),
                'date'     => $object->getEvent()->getStartDate(),
            );
        }

        // Send to each moderator of the object
        foreach ($object->getResource()->getModeratorList() as $moderator) {
            $messenger->targetToUser(
                $moderator->getId(),
                $message,
                $key,
                $level,
                $params
            );
        }

//        // Broadcast to all resource booking moderators
//        // TODO create a channel for services
//        $messenger->broadcastToService(
//            UserAccess::SERVICE_RESOURCE_BOOKING,
//            '*', // resource booking currently has no campus
//            UserAccess::ACCESS_FULL,
//            $message,
//            $key,
//            $level,
//            $params
//        );
        return $messenger;
    }

}
