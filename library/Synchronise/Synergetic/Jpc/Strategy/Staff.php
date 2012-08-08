<?php
/**
 * Synchronise_Synergetic_Jpc_Strategy_Staff
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Synchronise_Synergetic_Jpc_Strategy_Staff extends Synchronise_Synergetic_Jpc_Strategy_Abstract {
	protected $strategyTitle = "Update Schoolbox Staff Details Synchronisation";
    const SUPPORT_GROUP_ID = 11;
    const TEACH_GROUP_ID = 2;
	const STAFF_DEFAULT_CAMPUS = 1;

	/**
	 * Setup
	 */
	protected function setup() {
		$this->setVerboseFilter(new Synchronise_Synergetic_Jpc_Verbose_Staff());
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
		$newUser['alt_email'] = '';
        $newUser['local_email'] = 1;

		/* Campus config: needs to map to a campus id
		 */
		$newUser['defaultCampus'] = $this->getCampusFromCode($currentUser['StaffCampus']);
		$newUser['campus'] = array($this->getCampusFromCode($currentUser['StaffCampus']));

		/* Staff Group ID
		 */
		$newUser['group_id'] =  $this->getGroupIDFromCategory($currentUser['StaffCategory']);


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
        $teacherGroupList = array('TCHR','REP-TCHR','SCH_OFF','BUS MGR','Maint MGR','Princ_Sec','');

        if(in_array($category,$teacherGroupList))
            return self::TEACH_GROUP_ID;
        else
            return self::SUPPORT_GROUP_ID;
    }
}
