<?php
/**
 * Synchronise_Synergetic_StPauls_Strategy_Student
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * Adds Students in Synergetic to the Schoolbox User table.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_StPauls_Strategy_Student extends Synchronise_Synergetic_StPauls_Strategy_Abstract {
	protected $strategyTitle = "Update Schoolbox Student Details Synchronisation";

	/**
	 * Setup
	 */
	protected function setup() {
		$this->setVerboseFilter(new Synchronise_Synergetic_StPauls_Verbose_Student());
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
		$newUser['alt_email'] = $currentUser['StudentEmail'];
		$newUser['synergy_id'] = $currentUser['StudentID'];
		$newUser['local_email'] = 1;
		$newUser['eportfolio_enabled'] = 1;
		$findArray = array(' ', '-');
		$replaceArray = array('', '');

		if(strlen(trim($newUser['username']))==0){
			$newUser['username'] = strtolower(substr($newUser['firstname'],0,1).str_replace($findArray,$replaceArray,$newUser['lastname']));
		}
		/* Year level
		 */
		$newUser['year'] = $currentUser['StudentYearLevel'];

		/* Campus config: needs to map to a campus id
		 */
		$newUser['defaultCampus'] = $this->getCampusFromCode($currentUser['StudentCampus']);
		$newUser['campus'] = array($this->getCampusFromCode($currentUser['StudentCampus']));

		/* Student Group ID
		 */
		$newUser['group_id'] = $this->getGroupIDFromYearLevel($currentUser['StudentYearLevel']);

		/* Student House ID
		 */
		$newUser['house'] = $this->getHouseFromCode($currentUser['StudentHouse']);

		/* This person is not a superuser
		 */
		$newUser['su'] = false;
		
		$newUser['password'] = 'password';
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
