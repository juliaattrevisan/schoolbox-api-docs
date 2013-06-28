<?php
/**
 * Base sync for staff members.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class Synchronise_Base_Staff extends Synchronise_Base {

	/**
	 * Get a list of users to sync
	 *
	 * @return void
	 */
	public function generateList() {
		$this->list = $this->external_user_handler->getParentUserAccountInfo();
	}

	/**
	 * Set up the new user's details
	 *
	 * @param  $external_user
	 * @return array
	 */
	public function setupUserDetails($external_user) {
		$newUser = array();
		$newUser['username']      = $this->getUsernameFromUser($external_user);
		$newUser['title']         = $this->getTitleFromUser($external_user);
		$newUser['firstname']     = $this->getFirstNameFromUser($external_user);
		$newUser['lastname']      = $this->getLastNameFromUser($external_user);
		$newUser['synergy_id']    = $this->getExternalIdFromUser($external_user);
		$newUser['alt_email']     = $this->getAltEmailFromUser($external_user);
		$newUser['local_email']   = $this->getLocalEmailFromUser($external_user);
		$newUser['defaultCampus'] = $this->getCampusFromUser($external_user);
		$newUser['campus']        = array($newUser['defaultCampus']);
		$newUser['group_id']      = $this->getGroupIDFromUser($external_user);
		// filter out empty fields?
		return $newUser;
	}

	/**
	 * If the users exist in Synergy but not in Schoolbox, create an account for
	 * them
	 *
	 * @return void
	 */
	public function createIfExists() {
		$this->verbose_filter->intro();
		foreach ($this->list as $currentUser) {
			$this->executeCreateUpdate($this->setupUserDetails($currentUser));
		}
		$this->verbose_filter->summary();
	}

	/**
	 * Methods for retrieving Schoolbox fields from an external data set.
	 * These may be overridden on a per-sync basis.
	 */

	/**
	 * Gets Schoolbox username from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getUsernameFromUser($currentUser) {
		return $currentUser['NetworkLogin'];
	}

	/**
	 * Gets Schoolbox user's title (Mr., Ms., etc.) from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getTitleFromUser($currentUser) {
		return $currentUser['StaffTitle'];
	}

	/**
	 * Gets Schoolbox user's first name from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getFirstNameFromUser($currentUser) {
		return $currentUser['StaffPreferredName'];
	}

	/**
	 * Gets Schoolbox user's last name from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getLastNameFromUser($currentUser) {
		return $currentUser['StaffSurname'];
	}

	/**
	 * Gets Schoolbox user's external database ID from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getExternalIdFromUser($currentUser) {
		return $currentUser['StaffID'];
	}

	/**
	 * Gets Schoolbox user's alternate email address from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getAltEmailFromUser($currentUser) {
		return $currentUser['OccupEmail'];
	}

	/**
	 * Gets Schoolbox user's local email setting from the external data set.
	 *
	 * For most schools, this will be a school-wide setting instead of a
	 * per-user setting.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getLocalEmailFromUser($currentUser) {
		return 0;
	}

	/**
	 * Gets Schoolbox campus from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getCampusFromUser($currentUser) {
		return 1;
	}

	/**
	 * Gets Schoolbox group from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getGroupIDFromUser($currentUser) {
		return 1;
	}

}