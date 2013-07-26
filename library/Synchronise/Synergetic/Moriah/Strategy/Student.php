<?php
/**
 * Synchronise_Synergetic_Moriah_Strategy_Student
 *
 * Adds Students in Synergetic to the Schoolbox User table. Applies Campus based
 * on the Students' Year Level.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_Moriah_Strategy_Student extends Synchronise_Synergetic_Moriah_Strategy_Abstract {
	protected $strategyTitle = "Student Synchronisation";
	const STUDENT_GROUP_ID = 0; // unused

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

		// Login details
        // if the network login is present, use that, otherwise do not sync
		$newUser['username'] = $currentUser['NetworkLogin'];
		// $newUser['password'] = '1234';

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
		$newUser['year'] = $currentUser['StudentYearLevel'];

        // if they're preschool, we need to use the campus field from synergetic for mapping
        $campus = $this->getCampusFromYearLevel($currentUser['StudentYearLevel']);
        if($campus == self::P) {
            $campus = $this->getCampusFromCode($currentUser['StudentCampus']);
        }
        $newUser['defaultCampus'] = $campus;
        $newUser['campus'] = array($campus);

		/* Staff Group ID
		 */
		$newUser['group_id'] = $this->getGroupIDFromYearLevel($currentUser['StudentYearLevel']);

		/* This person is not a superuser
		 */
		//$newUser['su'] = false;

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

			/* Generate details about the new user
			 */
			$this->executeCreateUpdate($this->setupUserDetails($currentUser));
		}

		$this->getVerboseFilter()->summary();
	}
}