<?php
/**
 * Synchronise_Synergetic_Toorak_Strategy_Student
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * Adds Students in Synergetic to the Schoolbox User table.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Synergetic_Toorak_Strategy_Student extends Synchronise_Synergetic_Toorak_Strategy_Abstract {
	protected $strategyTitle = "Update Schoolbox Student Details Synchronisation";

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
		$this->collateList($this->getExternalUserHandler()->getStudentUserAccountInfo());
	}

	/**
	 * Set up the new user's details
	 *
	 * @param  $currentUser
	 * @return array
	 */
	public function setupUserDetails($currentUser) {
		$newUser = array();
		//
		// Username: always student ID
		$newUser['username']  = $currentUser['StudentID'];
		$newUser['title']     = '';
		$newUser['firstname'] = $currentUser['StudentPreferred'];
		$newUser['lastname']  = $currentUser['StudentSurname'];
		//
		// Don't touch email address, it's not in Synergetic
//		$newUser['alt_email']   = '';
		$newUser['synergy_id']  = $currentUser['StudentID'];
		$newUser['local_email'] = 0;
		$newUser['year']        = $currentUser['StudentYearLevel'];
		//
		// Campus: depends on year level
		$newUser['defaultCampus'] = $this->getCampusFromYearLevel($currentUser['StudentYearLevel']);
		$newUser['campus']        = array($newUser['defaultCampus']);
		$newUser['group_id']      = $this->getGroupIDFromYearLevel($currentUser['StudentYearLevel']);
		$newUser['house']         = $this->getHouseFromCode($currentUser['StudentHouse']);
		$newUser['su']            = false;
		//
		// Group/house membership?
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
}