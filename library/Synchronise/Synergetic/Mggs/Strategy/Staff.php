<?php
/**
 * Synchronise_Synergetic_Mggs_Strategy_Staff
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Synchronise_Synergetic_Mggs_Strategy_Staff extends Synchronise_Synergetic_Mggs_Strategy_Abstract {
	protected $strategyTitle = "Update Schoolbox Staff Details Synchronisation";
	const STAFF_GROUP_ID = 2;
	const STAFF_DEFAULT_CAMPUS = 1;

	/**
	 * Setup
	 */
	protected function setup() {
		$this->setVerboseFilter(new Synchronise_Synergetic_Mggs_Verbose_Staff());
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

		/* Login details
		 * We don't know how the password is given to us
		 */
		$newUser['username'] = $currentUser['NetworkLogin'];
		//$newUser['password'] = '1234';

		/* User details
		 */
		$newUser['title'] = '';
		$newUser['firstname'] = $currentUser['StaffPreferredName'];
		$newUser['lastname'] = $currentUser['StaffSurname'];
        $newUser['synergy_id'] = $currentUser['StaffID'];
		$newUser['alt_email'] = '';
        $newUser['local_email'] = 1;

		/* Campus config: needs to map to a campus id
		 */
		$newUser['defaultCampus'] = 1;
		$newUser['campus'] = array($this->getCampusFromCode($currentUser['StaffCampus']));

		/* Staff Group ID
		 */
		$newUser['group_id'] = self::STAFF_GROUP_ID;

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

			/* The drop down in Synergetic's Phone and Occupation tabs indicates
			 * which email the person prefers to use. Create a field in the
			 * $currentUser structure to remember this email address
			 */
			if ($currentUser['DefaultEmailCode'] == 'O') {
				$currentUser['preferredEmail'] = $currentUser['OccupEmail'];
			}
			else {
				$currentUser['preferredEmail'] = $currentUser['Email'];
			}

			/* Generate details about the new user
			 */
			$newUser = $this->setupUserDetails($currentUser);

			/* Default campus to 1, as per James' email
			 * but only if no campuses were determined
			 * (use array filter to remove empty elements)
			 */
			if (!count(array_filter($newUser['campus']))) {
				$newUser['campus'] = array(self::STAFF_DEFAULT_CAMPUS);
			}

			$this->executeCreateUpdate($newUser);
		}

		$this->getVerboseFilter()->summary();
	}
}
