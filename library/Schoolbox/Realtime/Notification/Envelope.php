<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */


namespace Schoolbox\Realtime\Notification;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Instance\InstanceBundle;
use Schoolbox\Campus;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * An envelope contains notifications...right?
 * Why is naming things so hard -_-
 *
 * Class Envelope
 *
 * @package Schoolbox\Realtime\Notification
 */
class Envelope extends InstanceBundle
{

    /**
     * @param int      $service
     * @param int      $id
     * @param int      $accessLevel
     * @param Instance $instance
     */
    public function broadcastToService($service, $id, $accessLevel, $instance)
    {
        $this->bind("service.$service.$id.$accessLevel", $instance);
    }

    /**
     * @param FolderModel|int $catId
     * @param int             $accessLevel
     * @param Instance        $instance
     */
    public function broadcastToCategory($catId, $accessLevel, $instance)
    {
        if ($catId instanceof FolderModel) {
            $catId = $catId->getId();
        }
        $this->broadcastToService(UserAccess::SERVICE_HOMEPAGE, $catId, $accessLevel, $instance);
    }


    /**
     * @param Campus|int $campusId
     * @param Instance   $instance
     */
    public function broadcastToCampus($campusId, $instance)
    {
        if ($campusId instanceof Campus) {
            $campusId = $campusId->getId();
        }
        $this->bind("campus.$campusId", $instance);
    }

    /**
     * @param Role|int $roleId
     * @param Instance $instance
     */
    public function broadcastToRole($roleId, $instance)
    {
        if ($roleId instanceof Role) {
            $roleId = $roleId->getId();
        }
        $this->bind("role.$roleId", $instance);
    }

    /**
     * @param string   $roleType
     * @param Instance $instance
     */
    public function broadcastToRoleType($roleType, $instance)
    {
        $this->bind("roletype.$roleType", $instance);
    }

    /**
     * @param int      $houseId
     * @param Instance $instance
     */
    public function broadcastToHouse($houseId, $instance)
    {
        $this->bind("house.$houseId", $instance);
    }

    /**
     * @param int      $resHouseId
     * @param Instance $instance
     */
    public function broadcastToResidentialHouse($resHouseId, $instance)
    {
        $this->bind("reshouse.$resHouseId", $instance);
    }

    /**
     * @param Instance $instance
     */
    public function broadcastToSuperusers($instance)
    {
        $this->bind("superuser", $instance);
    }

    /**
     * @param User|int $userId
     * @param Instance $instance
     * @param string   $delivery The delivery method:
     *                           one of the `Instance::DELIVERY_*` constants
     */
    public function targetToUser($userId, $instance, string $delivery = Instance::DELIVERY_NOTIFY)
    {
        if ($userId instanceof User) {
            $userId = $userId->getId();
        }
        $this->bind("user.$userId", $instance, $delivery);
    }

    /**
     * @param User|int $studentId
     * @param Instance $instance
     */
    public function targetToParent($studentId, $instance)
    {
        if ($studentId instanceof User) {
            $studentId = $studentId->getId();
        }
        $this->bind("parent.$studentId", $instance);
    }

    /**
     * Broadcast a message to everyone (at least, all staff/students/parents).
     *
     * @param Instance $instance
     */
    public function broadcastToEveryone($instance)
    {
        $this->broadcastToRoleType(Role::STAFF, $instance);
        $this->broadcastToRoleType(Role::STUDENT, $instance);
        $this->broadcastToRoleType(Role::PARENT, $instance);
    }

    /**
     * Retracts this message.
     *
     * @param Instance $instance
     */
    public function retract(Instance $instance)
    {
        $instance->setAction(Instance::ACTION_RETRACT_SINGLE);
        // Binding name is arbitrary, this won't ever get sent to anyone
        $this->bind(Instance::ACTION_RETRACT_SINGLE, $instance);
    }

    /**
     * Retracts this message, and all like it.
     *
     * @param Instance $instance
     */
    public function retractAllLike(Instance $instance)
    {
        $instance->setAction(Instance::ACTION_RETRACT_ALL);
        // Binding name is arbitrary, this won't ever get sent to anyone
        $this->bind(Instance::ACTION_RETRACT_ALL, $instance);
    }
}
