<?php
/**
 * Synchronise_Synergetic_Moriah_Strategy_Staff
 *
 * Adds Staff in Synergetic to the Schoolbox User table. Applies Campus based
 * on the Staff's campus field.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_Moriah_Strategy_Staff extends Synchronise_Synergetic_Moriah_Strategy_Abstract {
	protected $strategyTitle = "Staff Synchronisation";
	const STAFF_GROUP_ID = 2;
	const STAFF_DEFAULT_CAMPUS = 1;

	/**
	 * Get a list of users' Synergy IDs to sync
	 *
	 * @return void
	 */
	public function generateList() {
		$this->collateList($this->getExternalUserHandler()->getStaffUserAccountInfo());
	}

    /**
     * Do not move staff between groups
     *
     * @param $newUser
     * @return mixed
     */
    protected function userExists($newUser) {
        return $this->userExistsBySynergyAndGroup($newUser);
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
		 * If network login is blank user will be skipped
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

		/* Retrieve campuses from timetable definition (dirty hack)
		 */
        $campusList = $this->getCampus($currentUser['StaffID']);

        if(is_array($campusList)) {
            $newUser['defaultCampus'] = $campusList[0]['campus'];
            foreach( $campusList as $campus) {
                $newUser['campus'][] = $this->getCampusFromCode($campus['campus']);
            }
        }
        else if( $currentUser['StaffCampus'] ) {

            if($currentUser['StaffCampus'] == 'CG') { //Users in College General are put in all campuses
                $newUser['campus'] = array(3,4,5,6,7,8,9,10,11,12);
                $newUser['defaultCampus'] = 4;
            }
            else if($currentUser['StaffCampus'] == 'S') { //Users in Secondary School are put in MS & HS
                $newUser['campus'] = array(9,4);
                $newUser['defaultCampus'] = 4;
            }
            else {
                $newUser['defaultCampus'] = $this->getCampusFromCode($currentUser['StaffCampus']);
                $newUser['campus'] = array($this->getCampusFromCode($currentUser['StaffCampus']));
            }
        }
        else {
            //Just put staff in main if no campus retrieved from timetable
            $newUser['defaultCampus'] = self::CG;
            $newUser['campus'] = array(self::CG);
        }

        $newUser['campus'] = array_unique($newUser['campus']);


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

    /*
     * Fetches staff campus from timetable table
     */
    function getCampus($staffID) {
        return $this->getExternalUserHandler()->getCampusFromTimetable($staffID);
    }
}