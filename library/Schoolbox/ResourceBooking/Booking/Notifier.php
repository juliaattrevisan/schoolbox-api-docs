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
            case Mapper::METHOD_UPDATE:
                // Depends on the diff.
                // If only the status changed: trigger an event for that.
                // Otherwise, trigger the update event.
                $diff = $bundle->getEvent()->getDiff();
                if (isset($diff['status'])) {
                    list($new, $old) = $diff['status'];
                    switch ($new) {
                        case Status\Model::STATUS_APPROVED:
                            $bundle->attach('approved', self::IMMEDIATELY);
                            break;
                        case Status\Model::STATUS_REJECTED:
                            $bundle->attach('rejected', self::IMMEDIATELY);
                            break;
                    }
                    unset($diff['status']);
                }
                if (!empty($diff)) {
                    $bundle->attach('updated', self::IMMEDIATELY);
                }
                break;
            case Mapper::METHOD_DELETE:
                $bundle->attach('cancelled', self::IMMEDIATELY);
                break;
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
            '/resource-booking/item/'.$object->getResource()->getId()
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
        // It's possible the time was updated, too.
        $messenger = new Messenger();
        $message = new Message(
            ':user updated booking for :resource for :date',
            '/portrait.php?userId='.$user->getExternalId().'&size=square32',
            '/resource-booking/item/'.$object->getResource()->getId()
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


    /**
     * Notification for staff that a booking has been approved.
     *
     * @param Model $object
     * @param User  $user
     * @param array $diff
     *
     * @return Messenger
     */
    public function notifyStaffApproved(Model $object, User $user, $diff = null) {
        $messenger = new Messenger();
        $message = new Message(
            ':user approved your booking for :resource for :date',
            '/portrait.php?userId='.$user->getExternalId().'&size=square32',
            '/resource-booking/item/'.$object->getResource()->getId()
        );
        $messenger->broadcastToRoleType(
            'staff',
            $message,
            'resource_booking.'.$object->getId().'.approved',
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
     * Notification for staff that a booking has been rejected.
     *
     * @param Model $object
     * @param User  $user
     * @param array $diff
     *
     * @return Messenger
     */
    public function notifyStaffRejected(Model $object, User $user, $diff = null) {
        $messenger = new Messenger();
        $message = new Message(
            ':user rejected your booking for :resource for :date',
            '/portrait.php?userId='.$user->getExternalId().'&size=square32',
            '/resource-booking/item/'.$object->getResource()->getId()
        );
        $messenger->broadcastToRoleType(
            'staff',
            $message,
            'resource_booking.'.$object->getId().'.rejected',
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
    public function notifyStaffCancelled(Model $object, User $user, $diff = null) {
        $messenger = new Messenger();
        $message = new Message(
            ':user cancelled your booking for :resource for :date',
            '/portrait.php?userId='.$user->getExternalId().'&size=square32',
            '/resource-booking/item/'.$object->getResource()->getId()
        );
        $messenger->broadcastToRoleType(
            'staff',
            $message,
            'resource_booking.'.$object->getId().'.cancelled',
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
