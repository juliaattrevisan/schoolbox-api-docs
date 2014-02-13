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


class Authenticator {

	/**
	 * An instance of \Schoolbox\User
	 *
	 * @var \Schoolbox\User
	 */
	private $user = null;

	/**
	 * An instance of Event
	 *
	 * @var Event
	 */
	private $event = null;

	/**
	 * An instance of EventPermissionMapper
	 *
	 * @var EventPermissionMapper
	 */
	private $permissionMapper = null;

	/**
	 * An instance of \Schoolbox\Collection
	 *
	 * @var \Schoolbox\Collection
	 */
	private $allPermissions = null;

	/**
	 * Constructor
	 *
	 * @param \Schoolbox\User $user
	 * @param Event $event
	 */
	public function __construct(\Schoolbox\User $user, Event $event) {
		$this->setUser($user);
		$this->setEvent($event);

		$this->setPermissionMapper(new \EOTC\EventPermissionMapper());

		$this->setup();
	}

	/**
	 * Predetermine what permissions
	 */
	protected function setup() {
		$allPermissions = $this->getPermissionMapper()->findByUser($this->getUser());

		$this->setAllPermissions($allPermissions);
	}

	/**
	 * Determine whether the current user's permissions allow them
	 * to change the status of $event
	 */
	public function canPromote() {
		if ($this->getUser()->isSuperuser()) {
			return true;
		}

		/* Check that you have access to the campus as well as the status
		 */
		$hasMatchingPermission = false;
		$hasMatchingCampus = false;

		foreach($this->getAllPermissions() as $currentPermission) {

			/* If you haven't matched a campus, search for it
			 * Ignore loop if you have already matched a campus
			 */
			if (!$hasMatchingCampus) {
				foreach($this->getEvent()->getCampusCollection() as $currentCampus) {
					if ($currentPermission->getCampusId()->getId() == $currentCampus->getId()) {
						$hasMatchingCampus = true;
					}
				}
			}

			/* If you haven't matched a permission, search for it
			 */
			if ($currentPermission->getStatusId()->getId() == $this->getEvent()->getMetadata()->getStatus()->getId()) {
				$hasMatchingPermission = true;
			}
		}

		if ($hasMatchingCampus && $hasMatchingPermission) {
			return true;
		}

		return false;
	}

	/* Getters/Setters
	 */

	/**
	 * Set user
	 *
	 * @param \Schoolbox\User $user
	 */
	private function setUser($user) {
		$this->user = $user;
	}

	/**
	 * Get user
	 *
	 * @return \Schoolbox\User
	 */
	private function getUser() {
		return $this->user;
	}

	/**
	 * Set event
	 *
	 * @param \EOTC\Event $event
	 */
	private function setEvent($event) {
		$this->event = $event;
	}

	/**
	 * Get event
	 *
	 * @return \EOTC\Event
	 */
	private function getEvent() {
		return $this->event;
	}

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

	/**
	 * Set permissions
	 *
	 * @param \Schoolbox\Collection $permissions
	 */
	private function setPermissions($permissions) {
		$this->permissions = $permissions;
	}

	/**
	 * Get permissions
	 *
	 * @return \Schoolbox\Collection
	 */
	private function getPermissions() {
		return $this->permissions;
	}

	/**
	 * Set all permissions
	 *
	 * @param \Schoolbox\Collection $allPermissions
	 */
	private function setAllPermissions($allPermissions) {
		$this->allPermissions = $allPermissions;
	}

	/**
	 * Get all permissions
	 *
	 * @return \Schoolbox\Collection
	 */
	private function getAllPermissions() {
		return $this->allPermissions;
	}
}