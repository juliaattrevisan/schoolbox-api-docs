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
        /** @var Model $booking */
//        $booking = $bundle->getEvent()->getObject();
        if ($bundle->getMethod() === Mapper::METHOD_CREATE) {
            // What and When, not Who and How
            // What: Resource was booked
            // When: Right now
            $bundle->attach('created', self::IMMEDIATELY);
        }
        if ($bundle->getMethod() === Mapper::METHOD_UPDATE) {
            $bundle->attach('updated', self::IMMEDIATELY);
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
        $message = new Message(
            ':user booked :resource for :date',
            '/portrait.php?userId='.$user->getExternalId().'&size=square32',
            '/resource-booking/item/'.$object->getId()
        );
        $messenger->broadcastToRoleType(
            'staff',
            $message,
            'resource_booking.'.$object->getId().'.created',
            Logger::NOTICE,
            array(
                'user'     => $user->formatName(),
                'resource' => $object->getResource()->getName(),
                'date'     => $object->getEvent()->getStartDateTime(),
            )
        );
        return $messenger;
    }

    /**
     * Notification for staff that a booking has been updated.
     *
     * @param Model $object
     * @param User  $user
     * @param array $diff
     *
     * @return Messenger
     */
    public function notifyStaffUpdated(Model $object, User $user, $diff = null) {
        $messenger = new Messenger();
        $message = new Message(
            ':user updated booking for :resource for :date',
            '/portrait.php?userId='.$user->getExternalId().'&size=square32',
            '/resource-booking/item/'.$object->getId()
        );
        $messenger->broadcastToRoleType(
            'staff',
            $message,
            'resource_booking.'.$object->getId().'.updated',
            Logger::NOTICE,
            array(
                'user'     => $user->formatName(),
                'resource' => $object->getResource()->getName(),
                'date'     => $object->getEvent()->getStartDateTime(),
            )
        );
        return $messenger;
    }

}
