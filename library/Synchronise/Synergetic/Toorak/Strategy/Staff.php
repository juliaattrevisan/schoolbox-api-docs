<?php
/**
 * Synchronise_Synergetic_Toorak_Strategy_Staff
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Synergetic_Toorak_Strategy_Staff extends Synchronise_Synergetic_Toorak_Strategy_Abstract {
	protected $strategyTitle = "Update Schoolbox Staff Details Synchronisation";

	/**
	 * Setup
	 */
	protected function setup() {
		$this->setVerboseFilter(new Synchronise_Synergetic_Toorak_Verbose());
	}

	/**
	 * Get a list of users' Synergy IDs to sync
	 *
	 * @return void
	 */
	public function generateList() {
		$this->collateList($this->getExternalUserHandler()->getStaffUserAccountInfo());
	}

	/**
	 * Set up the new user's details
	 *
	 * @param  $currentUser
	 * @return array
	 */
	public function setupUserDetails($currentUser) {
		$newUser = array();
		$newUser['username']      = $currentUser['NetworkLogin'];
		$newUser['title']         = $currentUser['StaffTitle'];
		$newUser['firstname']     = $currentUser['StaffPreferredName'];
		$newUser['lastname']      = $currentUser['StaffSurname'];
		$newUser['synergy_id']    = $currentUser['StaffID'];
		$newUser['alt_email']     = $currentUser['OccupEmail'];
		$newUser['local_email']   = 0;
		$newUser['defaultCampus'] = $this->getCampusFromCode($currentUser['StaffCampus']);
		$newUser['campus']        = array($newUser['defaultCampus']);
		$newUser['group_id']      = self::STAFF_GROUP_ID;
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
			$newUser = $this->setupUserDetails($currentUser);
			if (!count(array_filter($newUser['campus']))) {
				$newUser['campus'] = array(self::STAFF_DEFAULT_CAMPUS);
			}
			$this->executeCreateUpdate($newUser);
		}
		$this->getVerboseFilter()->summary();
	}

}
