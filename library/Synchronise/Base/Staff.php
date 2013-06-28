<?php
/**
 * Base sync for staff members.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class Synchronise_Base_Staff extends Synchronise_Base {

//	protected $strategyTitle = "Update Schoolbox Staff Details Synchronisation";

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
	 * @param  $currentUser
	 * @return array
	 */
	public function setupUserDetails($currentUser) {
		$newUser = array();
		$newUser['username']      = $this->getUsernameFromUser($currentUser);
		$newUser['title']         = $this->getTitleFromUser($currentUser);
		$newUser['firstname']     = $this->getFirstNameFromUser($currentUser);
		$newUser['lastname']      = $this->getLastNameFromUser($currentUser);
		$newUser['synergy_id']    = $this->getSynergyIdFromUser($currentUser);
		$newUser['alt_email']     = $this->getAltEmailFromUser($currentUser);
		$newUser['local_email']   = $this->getLocalEmailFromUser($currentUser);
		$newUser['defaultCampus'] = $this->getCampusFromUser($currentUser);
		$newUser['campus']        = array($newUser['defaultCampus']);
		$newUser['group_id']      = $this->getGroupIDFromUser($currentUser);
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
		$this->getVerboseFilter()->intro();
		foreach($this->getList() as $currentUser) {
			$this->executeCreateUpdate($this->setupUserDetails($currentUser));
		}
		$this->getVerboseFilter()->summary();
	}

	public function getUsernameFromUser($currentUser) {
		return $currentUser['NetworkLogin'];
	}

	public function getTitleFromUser($currentUser) {
		return $currentUser['StaffTitle'];
	}

	public function getFirstNameFromUser($currentUser) {
		return $currentUser['StaffPreferredName'];
	}

	public function getLastNameFromUser($currentUser) {
		return $currentUser['StaffSurname'];
	}

	public function getSynergyIdFromUser($currentUser) {
		return $currentUser['StaffID'];
	}

	public function getAltEmailFromUser($currentUser) {
		return $currentUser['OccupEmail'];
	}

	public function getLocalEmailFromUser($currentUser) {
		return 0;
	}

	public function getCampusFromUser($currentUser) {
		return 1;
	}

	public function getGroupIDFromUser($currentUser) {
		return 1;
	}

}