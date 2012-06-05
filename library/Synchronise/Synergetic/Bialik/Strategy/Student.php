<?php
/**
 * Synchronise_Synergetic_Bialik_Strategy_Student
 *
 * Adds Students in Synergetic to the Schoolbox User table. Applies Campus based
 * on the Students' Year Level.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_Bialik_Strategy_Student extends Synchronise_Synergetic_Bialik_Strategy_Abstract {
	protected $strategyTitle = "Student Synchronisation";
	const STUDENT_GROUP_ID = 3;

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
		//$newUser['password'] = '1234';

		/* User details
		 */
		$newUser['title'] = '';
		$newUser['firstname'] = $currentUser['StudentPreferred'];
		$newUser['lastname'] = $currentUser['StudentSurname'];
        $newUser['alt_email'] = $newUser['username'].'@students.bialik.vic.edu.au';
        $newUser['synergy_id'] = $currentUser['StudentID'];
        $newUser['local_email'] = 0;

		/* Year level
		 */
		$newUser['year'] = $currentUser['StudentYearLevel'];

		/* Campus config: needs to map to a campus id
		 */
		$newUser['defaultCampus'] = 1;
		$newUser['campus'] = array($this->getCampusFromYearLevel($currentUser['StudentYearLevel']));

		/* Staff Group ID
		 */
		//$newUser['group_id'] = self::STUDENT_GROUP_ID;
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