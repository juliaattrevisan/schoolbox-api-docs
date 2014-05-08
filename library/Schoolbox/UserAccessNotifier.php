<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox;


use Schoolbox\Mapper\Exception;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;

class UserAccessNotifier extends AbstractModuleNotifier {

	const TRIGGER_JOINED    = 'joined';
	const TRIGGER_LEFT      = 'left';
	const TRIGGER_REQUESTED = 'requested';
	const TRIGGER_APPROVED  = 'approved';

	protected function buildTopic($eventLabel, $roleType) {
		// noop
	}

	/**
	 * Instantiate all your dependencies within yourself.
	 *
	 * @param mixed $dependencies
	 * @return void
	 */
	public function loadDependencies($dependencies = null) {
		$this->namespace = NSMapper::getInstance()->findOneBySlug(NSMapper::NS_GROUP);
	}

	/**
	 * @param TriggerBundle $bundle
	 * @return void
	 */
	public function bundleTriggers($bundle) {
		$method = $bundle->getMethod();

		/** @var UserAccess $userAccess */
		$userAccess = $bundle->getEvent()->getObject();

		try {
			/** @var Group $group */
			$group = GroupMapper::getInstance()->findOneByColumn('cid', $userAccess->getItemId());
		}
		catch(Exception $e) {
			// no group, do nothing
			return;
		}

		// new useraccess was created
		if($method == Mapper::METHOD_CREATE) {
			// if this is a request to join a moderated group
			if($group->isModerated() && $userAccess->getAccessLevel() == UserAccess::ACCESS_DENY) {
				$bundle->attach(self::TRIGGER_REQUESTED, self::IMMEDIATELY);
			}
			// if public or private, or not ACCESS_DENY
			else {
				$bundle->attach(self::TRIGGER_JOINED, self::IMMEDIATELY);
			}

		}
		// useraccess revoked
		elseif($method == Mapper::METHOD_DELETE) {
			$bundle->attach(self::TRIGGER_LEFT, self::IMMEDIATELY);
		}
		// access level changed (usually moderation)
		elseif($method == Mapper::METHOD_UPDATE) {
			$bundle->attach(self::TRIGGER_APPROVED, self::IMMEDIATELY);
		}
	}


	/* ASYNC METHODS */

	/**
	 * @param UserAccess $userAccess
	 * @param User $user
	 * @return Messenger
	 */
	public function notifyStaffJoined($userAccess, $user) {

	}

	/**
	 * @param UserAccess $userAccess
	 * @param User $user
	 * @return Messenger
	 */
	public function notifyStaffLeft($userAccess, $user) {

	}

	/**
	 * @param UserAccess $userAccess
	 * @param User $user
	 * @return Messenger
	 */
	public function notifyStaffRequested($userAccess, $user) {

	}


	/**
	 * @param string $roleType
	 * @param UserAccess $userAccess
	 * @param User $user
	 * @param array $diff
	 * @return Messenger
	 */
	protected function notifyApproved($roleType, $userAccess, $user, $diff = null) {

	}


	/**
	 * @param UserAccess $userAccess
	 * @param User $user
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStaffApproved($userAccess, $user, $diff = null) {

	}


	/**
	 * @param UserAccess $userAccess
	 * @param User $user
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStudentApproved($userAccess, $user, $diff = null) {

	}


	/**
	 * @param UserAccess $userAccess
	 * @param User $user
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyParentApproved($userAccess, $user, $diff = null) {

	}


}