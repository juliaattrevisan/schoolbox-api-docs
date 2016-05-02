<?php
/**
 * Authenticator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;
use \Schoolbox\User;

class Authenticator {

	/**
	 * An instance of EventPermissionMapper
	 *
	 * @var EventPermissionMapper
	 */
	private $permissionMapper = null;

	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		$this->setPermissionMapper(new \EOTC\EventPermissionMapper());

		$this->setup();
	}

	/**
	 * Predetermine what permissions
	 */
	protected function setup() {

	}

	/**
	 * Determine whether the specified user can spawn an ETOC event
	 *
	 * @param User $user
	 * @return bool
	 */
	public function isEOTCCreator(User $user) {
		if ($user->getRole()->isStaff() || $user->isSuperuser()) {
			return true;
		}

		return false;
	}

	/**
	 * Determine whether the specified user is an EOTC Administrator
	 * (a staff user with at least one valid EOTC Permission assigned)
	 *
	 * @param User $user
	 * @return bool
	 */
	public function isEOTCAdministrator(User $user) {
		if ($user->isSuperuser()) {
			return true;
		}

		$allPermissions = $this->getPermissionMapper()->findByUser($user);

		if ($allPermissions->count()) {
			return true;
		}

		return false;
	}

	/**
	 * Determine whether the specified user is an EOTC Subscribed User
	 * This encompasses Attendance Officer, Operations Officer, etc
	 * and whatever other roles STK uses to manage their stuff
	 *
	 * @param User $user
	 * @param Event $event
	 * @return bool
	 */
	public function isSubscribedUser(User $user, Event $event)
	{
		$allSubscriptions = $this->getPermissionMapper()->findSubscriptionsByUser($user);

		foreach ($allSubscriptions as $currentSubscription) {
			$inCampus = $this->isPermissionInCampus($currentSubscription, $event);
			$hasStatus = $this->isPermissionInStatus($currentSubscription, $event);

			if ($inCampus && $hasStatus) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Subscribed User has subscribed to the Event's campus
	 *
	 * @param EventPermission $permission
	 * @param Event $event
	 * @return bool
	 */
	private function isPermissionInCampus(EventPermission $permission, Event $event)
	{
		foreach ($event->getCampusCollection() as $currentCampus) {
			if ($permission->getCampusId()->getId() == $currentCampus->getId()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Subscribed User has subscribed to the Event's current status
	 *
	 * @param EventPermission $permission
	 * @param Event $event
	 * @return bool
	 */
	private function isPermissionInStatus(EventPermission $permission, Event $event)
	{
		return $permission->getStatusId()->getId() == $event->getMetadata()->getStatus()->getId();
	}

	/**
	 * Allow event authors to modify status of events from In Principle
	 * to the next status
	 *
	 * @param User $user
	 * @param Event $event
	 * @return bool
	 */
	public function canSelfPromote(User $user, Event $event) {
		if ($event->getMetadata()->getStatus()->isInPrinciple()) {
			if ($event->getAuthor()->getId() == $user->getId()) {
				return true;
			}

			/* Teacher in Charge can self promote, too
			 */
			if ($event->getTeacherInCharge()->getId() == $user->getId()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether the current user's permissions allow them
	 * to change the status of $event
	 *
	 * @param User $user
	 * @param Event $event
	 * @return bool
	 */
	public function canPromote(User $user, Event $event) {
		if ($user->isSuperuser()) {
			return true;
		}

		/* Allow all EOTC administrators to see Approved events
		 */
		if ($event->getMetadata()->getStatus()->isApproved()) {
			return true;
		}

		/* Allow all EOTC administrators to see Rejected events
		 */
		if ($event->getMetadata()->getStatus()->isRejected()) {
			return true;
		}

		$allPermissions = $this->getPermissionMapper()->findByUser($user);

		/* Check that you have access to the campus as well as the status
		 */
		$hasMatchingPermission = false;
		$hasMatchingCampus = false;

		foreach($allPermissions as $currentPermission) {

			/* If you haven't matched a campus, search for it
			 * Ignore loop if you have already matched a campus
			 */
			if (!$hasMatchingCampus) {
				foreach($event->getCampusCollection() as $currentCampus) {
					if ($currentPermission->getCampusId()->getId() == $currentCampus->getId()) {
						$hasMatchingCampus = true;
					}
				}
			}

			/* If you haven't matched a permission, search for it
			 */
			if ($currentPermission->getStatusId()->getId() == $event->getMetadata()->getStatus()->getId()) {
				$hasMatchingPermission = true;
			}
		}

		if ($hasMatchingCampus && $hasMatchingPermission) {
			return true;
		}

		return false;
	}

	/**
	 * Gets whether the requested status change is allowed.
	 *
	 * Currently, the only barred change is that events may not be approved if
	 * there are no students in the event.
	 *
	 * @param Event       $event
	 * @param EventStatus $newStatus
	 *
	 * @return bool
	 */
	public function isStatusChangeAllowed(Event $event, EventStatus $newStatus)
	{
		$oldStatusId = $event->getMetadata()->getStatus()->getId();
		$newStatusId = $newStatus->getId();
		if ($oldStatusId != $newStatusId) {
			if ($newStatusId == EventStatusMapper::STATUS_APPROVED) {
				if (count($event->getMetadata()->getStudentAttendees()) === 0) {
					return false;
				}
			}
		}
		return true;
	}

	/* Getters/Setters
	 */

	/**
	 * Set permission mapper
	 *
	 * @param \EOTC\EventPermissionMapper $permissionMapper
	 */
	private function setPermissionMapper($permissionMapper) {
		$this->permissionMapper = $permissionMapper;
	}

	/**
	 * Get permission mapper
	 *
	 * @return \EOTC\EventPermissionMapper
	 */
	private function getPermissionMapper() {
		return $this->permissionMapper;
	}
}