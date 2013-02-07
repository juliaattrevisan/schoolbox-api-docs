<?php
/**
 * Synchronise_Synergetic_Balcombe_Strategy_Staff
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Synchronise_Synergetic_Balcombe_Strategy_Staff extends Synchronise_Synergetic_Balcombe_Strategy_Abstract {
	protected $strategyTitle = "Update Schoolbox Staff Details Synchronisation";


	/**
	 * Setup
	 */
	protected function setup() {
		$this->setVerboseFilter(new Synchronise_Synergetic_Balcombe_Verbose_Staff());
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

        // User details
        $newUser['username'] = $currentUser['NetworkLogin'];
		$newUser['title'] = $currentUser['StaffTitle'];
		$newUser['firstname'] = $currentUser['StaffPreferredName'];
		$newUser['lastname'] = $currentUser['StaffSurname'];
        $newUser['synergy_id'] = $currentUser['StaffID'];
		$newUser['alt_email'] = '';
        $newUser['local_email'] = 1;

		// Campus config: needs to map to a campus id
		$newUser['defaultCampus'] = $this->getCampusFromCode($currentUser['StaffCampus']);
		$newUser['campus'] = array($this->getCampusFromCode($currentUser['StaffCampus']));

		// Staff Group ID
        // TODO determine how to map to the 10 different groups, or perhaps not at all
		$newUser['group_id'] =  $this->getGroupIDFromCategory($currentUser['StaffCategoryType']);

		return $newUser;
	}

	/**
	 * If the users exist in Synergy but not in Schoolbox, create an account for them
	 *
	 * @return void
	 */
	public function createIfExists() {
		$this->getVerboseFilter()->intro();

		foreach($this->getList() as $currentUser) {
			// Generate details about the new user
			$newUser = $this->setupUserDetails($currentUser);

			// Default campus to 1, but only if no campuses were determined (use array filter to remove empty elements)
			if (!count(array_filter($newUser['campus']))) {
				$newUser['campus'] = array(self::DEFAULT_CAMPUS);
			}

			$this->executeCreateUpdate($newUser);
		}

		$this->getVerboseFilter()->summary();
	}

    /**
     * Converts Synergetic StaffCategoryType to admin or teacher group
     *
     * @param $category
     * @return bool|int
     */
    protected function getGroupIDFromCategory($category) {
        if($category == 'TEACHER') {
            return self::TEACH_GROUP_ID;
        }
        return false;
    }
}
