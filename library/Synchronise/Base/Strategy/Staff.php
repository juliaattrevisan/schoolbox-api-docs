<?php
/**
 * Base sync for staff members.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class Synchronise_Base_Strategy_Staff extends Synchronise_Base_Strategy {

	/**
	 * Get a list of users to sync
	 *
	 * @return void
	 */
	public function generateList() {
		$this->list = $this->external_user_handler->getStaffUserAccountInfo();
	}

	/**
	 * Methods for retrieving Schoolbox fields from an external data set.
	 * These may be overridden on a per-sync basis.
	 */

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
	 * Gets Schoolbox default campus from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getDefaultCampusFromUser($currentUser) {
		return 1;
	}

	/**
	 * Gets Schoolbox campus list from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return array[int]
	 */
	public function getCampusListFromUser($currentUser) {
		return array($this->getDefaultCampusFromUser($currentUser));
	}

	/**
	 * Gets Schoolbox group from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getGroupIDFromUser($currentUser) {
		return self::DEFAULT_STAFF_GROUP;
	}

	/**
	 * Gets this user's year from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getYearFromUser($currentUser) {
		return null;
	}

}