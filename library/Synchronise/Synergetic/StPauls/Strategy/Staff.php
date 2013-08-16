<?php
/**
 * Synchronise_Synergetic_StPauls_Strategy_Staff
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Synchronise_Synergetic_StPauls_Strategy_Staff extends Synchronise_Synergetic_StPauls_Strategy_Abstract {
	protected $strategyTitle = "Update Schoolbox Staff Details Synchronisation";
    const ADMIN_GROUP_ID = 1;
    const TEACH_GROUP_ID = 2;
	const STAFF_DEFAULT_CAMPUS = 1;

	/**
	 * Setup
	 */
	protected function setup() {
		$this->setVerboseFilter(new Synchronise_Synergetic_StPauls_Verbose_Staff());
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
		$username = trim($currentUser['NetworkLogin']);
		if (strlen($username) === 0) {
			$username = strtolower($currentUser['StaffPreferredName'].'.'.$currentUser['StaffSurname']);
		}
		$newUser['username'] = $username;

		/* User details
		 */
		$newUser['title'] = $currentUser['StaffTitle'];
		$newUser['firstname'] = $currentUser['StaffPreferredName'];
		$newUser['lastname'] = $currentUser['StaffSurname'];
		$newUser['synergy_id'] = $currentUser['StaffID'];
		$newUser['alt_email'] = '';
		$newUser['local_email'] = 1;

		$findArray = array(' ', '-');
		$replaceArray = array('', '');

		if(strlen(trim($newUser['username'])) == 0) {
			$newUser['username'] = strtolower(substr($newUser['firstname'],0,1).str_replace($findArray,$replaceArray,$newUser['lastname']));
		}

		/* Campus config: needs to map to a campus id
		 */
		$newUser['defaultCampus'] = $this->getCampusFromCode($currentUser['StaffCampus']);
		$newUser['campus'] = array($this->getCampusFromCode($currentUser['StaffCampus']));

		/* Staff Group ID
		 */
		$newUser['group_id'] =  $this->getGroupIDFromCategory($currentUser['StaffCategoryType']);
		if($newUser['username'] != 'alaress'){
			$newUser['password'] = 'password';
		}

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

    //Converts Synergetic StaffCategoryType to admin or teacher group
    protected function getGroupIDFromCategory($category) {
        if($category == 'TEACHER')
            return self::TEACH_GROUP_ID;
        else
            return self::ADMIN_GROUP_ID;
    }
}
