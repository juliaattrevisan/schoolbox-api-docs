<?php
/**
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
class Synchronise_Edumate_Tigs_Strategy_Student extends Synchronise_Edumate_Tigs_Strategy_Abstract {

	/**
	 * Get a list of records to process.
	 *
	 * Copy-pasted, should be in base class.
	 *
	 * @return void
	 */
	public function generateList() {
		$this->collateList($this->getExternalUserHandler()->getStudentUserAccountInfo());
	}

	/**
	 * Set up the new user's details
	 *
	 * This should be the only method that needs to be overridden.
	 *
	 * @param  $currentUser
	 * @return array
	 */
	public function setupUserDetails($currentUser) {
		$newUser = array();

		// login details
		$newUser['username'] = $currentUser['NetworkLogin'];

		// user attributes
		$newUser['title']         = '';
		$newUser['firstname']     = $currentUser['StudentPreferred'];
		$newUser['lastname']      = $currentUser['StudentSurname'];
		$newUser['alt_email']     = $currentUser['StudentEmail'];
		$newUser['synergy_id']    = $currentUser['StudentID'];
		$newUser['local_email']   = 1;
		$newUser['year']          = $this->getYearCodeFromYearLevel($currentUser['StudentYearLevel']);
		//
		// No houses
//		$newUser['house']         = $this->getHouseFromCode($currentUser['StudentHouse']);
		$campus                   = $this->getCampusFromYearLevel($currentUser['StudentYearLevel']);
		$newUser['defaultCampus'] = $campus;
		$newUser['campus']        = array($campus);
		$newUser['group_id']      = $this->getGroupIDFromYearLevel($currentUser['StudentYearLevel']);

		$newUser['eportfolio_enabled'] = false;

		// students aren't superusers
		//$newUser['su'] = false;

		return $newUser;
	}
}
