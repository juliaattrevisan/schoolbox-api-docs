<?php
/**
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
class Synchronise_Edumate_Tigs_Strategy_Staff extends Synchronise_Edumate_Tigs_Strategy_Abstract {

	/**
	 * Get a list of records to process.
	 *
	 * Copy-pasted, should be in base class.
	 *
	 * @return void
	 */
	public function generateList() {
		$this->collateList($this->getExternalUserHandler()->getStaffUserAccountInfo());
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

		// User details
		$newUser['username']   = $currentUser['NetworkLogin'];
		$newUser['title']      = $currentUser['StaffTitle'];
		$newUser['firstname']  = $currentUser['StaffPreferredName'];
		$newUser['lastname']   = $currentUser['StaffSurname'];
		$newUser['synergy_id'] = $currentUser['StaffID'];
		$newUser['alt_email']  = $currentUser['Email'];

		// Campus config: needs to map to a campus id
		$newUser['defaultCampus'] = $this->getCampusFromCode($currentUser['StaffCampus']);
		$newUser['campus']        = array($this->getCampusFromCode($currentUser['StaffCampus']));

		$newUser['group_id']      = 1;
		return $newUser;
	}

	/**
	 * Process them
	 *
	 */
	public function createIfExists() {
		$this->getVerboseFilter()->intro();

		foreach ($this->getList() as $currentUser) {
			// Generate details about the new user
			$newUser = $this->setupUserDetails($currentUser);
			$this->executeCreateUpdate($newUser);
		}

		$this->getVerboseFilter()->summary();
	}

}
