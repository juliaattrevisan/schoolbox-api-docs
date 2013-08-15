<?php
/**
 * Synchronise_Synergetic_StMichaels_Strategy_Staff
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Synchronise_Synergetic_StMichaels_Strategy_Staff extends Synchronise_Synergetic_StMichaels_Strategy_Abstract {
	protected $strategyTitle = "Update Schoolbox Staff Details Synchronisation";
    const ADMIN_GROUP_ID = 1;
    const TEACH_GROUP_ID = 2;
	const STAFF_DEFAULT_CAMPUS = 1;

	/**
	 * Setup
	 */
	protected function setup() {
		$this->setVerboseFilter(new Synchronise_Synergetic_StMichaels_Verbose_Staff());
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

		/* User details
		 */
		$newUser['title'] = $currentUser['StaffTitle'];
		$newUser['firstname'] = $currentUser['StaffPreferredName'];
		$newUser['lastname'] = $currentUser['StaffSurname'];
		$newUser['synergy_id'] = $currentUser['StaffID'];
		$newUser['alt_email'] = $currentUser['OccupEmail'];
		$newUser['local_email'] = 0;

		/* Campus config: needs to map to a campus id
		 */
		$newUser['defaultCampus'] = $this->getCampusFromCode($currentUser['StaffCampus']);
		$newUser['campus']        = array($newUser['defaultCampus']);

		//
		// Campus list: should not override what's already there. [SB-1916]
		$schoolbox_user = ($this->getUserHandler()->getBySynergy($currentUser['StaffID']));
		if (!empty($schoolbox_user)) {
			if (!empty($schoolbox_user['uid'])) {
				$newUser['campus'] = $this->getUserHandler()->getUserCampus($schoolbox_user['uid']);
			}
		}

		/* Default campus to 1, as per James' email
		 * but only if no campuses were determined
		 * (use array filter to remove empty elements)
		 */
		if (!count(array_filter($newUser['campus']))) {
			$newUser['campus'] = array(self::STAFF_DEFAULT_CAMPUS);
		}

		/* Staff Group ID
		 */
		$newUser['group_id'] =  $this->getGroupIDFromCategory($currentUser['StaffCategoryType']);


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

    //Converts Synergetic StaffCategoryType to admin or teacher group
    protected function getGroupIDFromCategory($category) {

        if($category == 'TEACHER')
            return self::TEACH_GROUP_ID;
        else
            return self::ADMIN_GROUP_ID;
    }
}
