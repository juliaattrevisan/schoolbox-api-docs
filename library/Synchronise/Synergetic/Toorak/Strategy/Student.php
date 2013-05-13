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

		/* Login details
		 * We don't know how the password is given to us
		 */
		$newUser['username'] = $currentUser['NetworkLogin'];

		/* User details
		 */
		$newUser['title'] = '';
		$newUser['firstname'] = $currentUser['StudentPreferred'];
		$newUser['lastname'] = $currentUser['StudentSurname'];
        $newUser['alt_email'] = $currentUser['StudentOccupEmail'];
        $newUser['synergy_id'] = $currentUser['StudentID'];
        $newUser['local_email'] = 0;

		/* Year level
		 */
		$newUser['year'] = $this->getYear($currentUser['StudentYearLevel']);

		/* Campus config: needs to map to a campus id
		 */
		$newUser['defaultCampus'] = $this->getCampusFromCode($currentUser['StudentCampus']);
		$newUser['campus'] = array($this->getCampusFromCode($currentUser['StudentCampus']));

		/* Staff Group ID
		 */
		$newUser['group_id'] = $this->getGroupIDFromYearLevel($currentUser['StudentYearLevel']);

		/* This person is not a superuser
		 */
		//$newUser['su'] = false;

		return $newUser;
	}

    protected function getYear($year) {
        if($year == '40')
            return '4yo Kinder';
        else if($year == '30')
            return '3yo Kinder';
        else
            return $year;
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

			/* Generate details about the new user
			 */
			$this->executeCreateUpdate($this->setupUserDetails($currentUser));
		}

		$this->getVerboseFilter()->summary();
	}
}