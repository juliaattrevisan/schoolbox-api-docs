<?php
/**
 * Synchronise_Synergetic_Seymour_Strategy_Parent
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_Seymour_Strategy_Parent extends Synchronise_Synergetic_Seymour_Strategy_Abstract {
	protected $strategyTitle = "Update Schoolbox Parent Details Synchronisation";



	/**
	 * Setup
	 */
	protected function setup() {
		$this->setVerboseFilter(new Synchronise_Synergetic_Seymour_Verbose_Parent());
	}

	/**
	 * Get a list of users' Synergy IDs to sync
	 *
	 * @return void
	 */
	public function generateList() {
        $this->collateList($this->getExternalUserHandler()->getParentUserAccountInfo());
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
        $newUser['username']   = $currentUser['StudentContactID'];
        $newUser['synergy_id'] = $currentUser['StudentContactID'];
        $newUser['title']      = $currentUser['StudentContactTitle'];
        $newUser['firstname']  = $currentUser['StudentContactPreferred'];
        $newUser['lastname']   = $currentUser['StudentContactSurname'];
        $newUser['alt_email']  = $currentUser['StudentContactDefaultEmail'];

        // get campuses of attached children
        $newUser['campus']        = $this->getParentCampus($currentUser['StudentContactID']);
        $newUser['defaultCampus'] = $newUser['campus'][0];
        $newUser['group_id']      = self::PARENT_GROUP_ID;
        $newUser['su']            = false;

        return $newUser;
    }

    /**
     * Set up the new user's spouse's details
     *
     * @param $currentUser
     * @return array
     */
    public function setupSpouseDetails($currentUser) {
        $newUser = array();

        if($currentUser['StudentContactSpouseID']) {
            // User details
            $newUser['username']   = $currentUser['StudentContactSpouseID'];
            $newUser['synergy_id'] = $currentUser['StudentContactSpouseID'];
            $newUser['title']      = $currentUser['StudentContactSpouseTitle'];
            $newUser['firstname']  = $currentUser['StudentContactSpousePreferred'];
            $newUser['lastname']   = $currentUser['StudentContactSpouseSurname'];
            $newUser['alt_email']  = $currentUser['StudentContactSpouseDefaultEmail'];

            // get campuses of kids
            $newUser['campus']        = $this->getParentCampus($currentUser['StudentContactSpouseID']);
            $newUser['defaultCampus'] = $newUser['campus'][0];
            $newUser['group_id']      = self::PARENT_GROUP_ID;
            $newUser['su']            = false;
            return $newUser;
        }
        else {
         return false;
        }

    }

    /**
     * Fetches an array of campus based on parents students in the school
     *
     * @param $parentID
     * @return array
     */
    function getParentCampus($parentID) {
        $campus = array();
        $students = $this->getExternalUserHandler()->getEnrolledStudents($parentID);
        if(is_array($students)) {
            foreach($students as $child) {
                $childUser = $this->getUserHandler()->getBySynergy($child['RelatedID']);
                $campuses = $this->getUserHandler()->getUserCampus($childUser['uid']);
                $campus = array_merge($campus, $campuses);
            }
            $campus = array_unique($campus);
        }
        return $campus;
    }

    /**
     * If the users exist in Synergy but not in Schoolbox, create an account for
     * them
     *
     * @return void
     */
    public function createIfExists() {
        $previousUser = null;

        $this->getVerboseFilter()->intro();

        foreach($this->getList() as $currentUser) {
            $newUser = $this->setupUserDetails($currentUser);
            $newSpouse = $this->setupSpouseDetails($currentUser);

            /* Update previous user
                 */
            if ($this->userExistsBySynergyAndGroup($newUser)) {
                $this->executeCreateUpdate($newUser);
            }
            if (is_array($newSpouse) && $this->userExistsBySynergyAndGroup($newSpouse)) {
                $this->executeCreateUpdate($newSpouse);
            }
    }

        $this->getVerboseFilter()->summary();
    }
}