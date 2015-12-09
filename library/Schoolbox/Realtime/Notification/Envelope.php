<?php
 /**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


namespace Schoolbox\Realtime\Notification;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Instance\InstanceBundle;
use Schoolbox\Campus;
use Schoolbox\Resource\Folder\Model as CategoryModel;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Permission\Access\UserAccess;

/**
 * An envelope contains notifications...right?
 * Why is naming things so hard -_-
 *
 * This class supersedes Messenger
 *
 * Class Envelope
 *
 * @package Schoolbox\Realtime\Notification
 */
class Envelope extends InstanceBundle {

	/**
	 * @param int $service
	 * @param int $id
	 * @param int $accessLevel
	 * @param Instance $instance
	 */
	public function broadcastToService($service, $id, $accessLevel, $instance) {
		$this->bind("service.$service.$id.$accessLevel", $instance);
	}

	/**
	 * @param CategoryModel|int $catId
	 * @param int          $accessLevel
	 * @param Instance     $instance
	 */
	public function broadcastToCategory($catId, $accessLevel, $instance) {
		if($catId instanceof CategoryModel) {
			$catId = $catId->getId();
		}
		$this->broadcastToService(UserAccess::SERVICE_HOMEPAGE, $catId, $accessLevel, $instance);
	}


	/**
	 * @param Campus|int $campusId
	 * @param Instance   $instance
	 */
	public function broadcastToCampus($campusId, $instance) {
		if($campusId instanceof Campus) {
			$campusId = $campusId->getId();
		}
		$this->bind("campus.$campusId", $instance);
	}

	/**
	 * @param Role|int $roleId
	 * @param Instance $instance
	 */
	public function broadcastToRole($roleId, $instance) {
		if($roleId instanceof Role) {
			$roleId = $roleId->getId();
		}
		$this->bind("role.$roleId", $instance);
	}

	/**
	 * @param string $roleType
	 * @param Instance $instance
	 */
	public function broadcastToRoleType($roleType, $instance) {
		$this->bind("roletype.$roleType", $instance);
	}

	/**
	 * @param int $houseId
	 * @param Instance $instance
	 */
	public function broadcastToHouse($houseId, $instance) {
		$this->bind("house.$houseId", $instance);
	}

	/**
	 * @param int $resHouseId
	 * @param Instance $instance
	 */
	public function broadcastToResidentialHouse($resHouseId, $instance) {
		$this->bind("reshouse.$resHouseId", $instance);
	}

	/**
	 * @param Instance $instance
	 */
	public function broadcastToSuperusers($instance) {
		$this->bind("superuser", $instance);
	}

	/**
	 * @param User|int $userId
	 * @param Instance $instance
	 * @param bool     $force
	 */
	public function targetToUser($userId, $instance, $force = false) {
		if($userId instanceof User) {
			$userId = $userId->getId();
		}
		$this->bind("user.$userId", $instance, $force);
	}

	/**
	 * @param User|int $studentId
	 * @param Instance $instance
	 */
	public function targetToParent($studentId, $instance) {
		if($studentId instanceof User) {
			$studentId = $studentId->getId();
		}
		$this->bind("parent.$studentId", $instance);
	}

} 
