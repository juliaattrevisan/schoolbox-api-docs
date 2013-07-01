<?php
/**
 * Base sync for students.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class Synchronise_Base_Strategy_Student extends Synchronise_Base_Strategy {

	/**
	 * Get a list of students to sync
	 *
	 * @return void
	 */
	public function generateList() {
		$this->list = $this->external_user_handler->getStudentUserAccountInfo();
	}

	/**
	 * Methods for retrieving Schoolbox fields from an external data set.
	 * These may be overridden on a per-sync basis.
	 *
	 * If any of them return null, then that field value is ignored.
	 */

	/**
	 * Gets Schoolbox user's title (Mr., Ms., etc.) from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getTitleFromUser($currentUser) {
		return $currentUser['StudentTitle'];
	}

	/**
	 * Gets Schoolbox user's first name from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getFirstNameFromUser($currentUser) {
		return $currentUser['StudentPreferred'];
	}

	/**
	 * Gets Schoolbox user's last name from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getLastNameFromUser($currentUser) {
		return $currentUser['StudentSurname'];
	}

	/**
	 * Gets Schoolbox user's external database ID from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getExternalIdFromUser($currentUser) {
		return $currentUser['StudentID'];
	}

	/**
	 * Gets Schoolbox user's alternate email address from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getAltEmailFromUser($currentUser) {
		if (strlen(trim($currentUser['StudentOccupEmail']))) {
			return $currentUser['StudentOccupEmail'];
		}
		return null;
	}

	/**
	 * Gets Schoolbox group from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getGroupIDFromUser($currentUser) {
		return self::DEFAULT_STUDENT_GROUP;
	}

	/**
	 * Gets student's year from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getYearFromUser($currentUser) {
		return $currentUser['StudentYearLevel'];
	}
}