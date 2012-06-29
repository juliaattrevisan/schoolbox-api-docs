<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Synchronise_Synergetic_Wenona_Strategy_Staff extends Synchronise_Synergetic_Wenona_Strategy_Abstract {
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

        // Login details - use the internal email to determine the username for staff (not provided elsewhere)
        $newUser['username'] = substr($currentUser['OccupEmail'], 0, strpos($currentUser['OccupEmail'], '@'));
     // $newUser['password'] = '1234';

        // User details
        $newUser['title']              = $currentUser['StaffTitle'];
        $newUser['firstname']          = $currentUser['StaffPreferredName'];
        $newUser['lastname']           = $currentUser['StaffSurname'];
        $newUser['synergy_id']         = $currentUser['StaffID'];
        $newUser['alt_email']          = $currentUser['OccupEmail'];
        $newUser['local_email']        = self::STAFF_LOCAL_EMAIL;
        $newUser['defaultCampus']      = self::STAFF_DEFAULT_CAMPUS;
        // $newUser['campus']             = $this->getCampusFromCode($currentUser['StaffCampus']);
        $newUser['group_id']           = $this->getGroupIDFromCategory($currentUser['StaffCategoryType']);
        if(self::STAFF_EPORTFOLIO != -1) {
            $newUser['eportfolio_enabled'] = self::STAFF_EPORTFOLIO;
        }

        // staff are not superusers by default
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

            if($currentUser['ActiveFlag'] === 0) {
                continue;
            }

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

            NOT SYNCING CAMPUS FOR STAFF

            if(!is_array($newUser['campus']) || !count(array_filter($newUser['campus']))) {
                $newUser['campus'] = array(self::STAFF_DEFAULT_CAMPUS);
            }
                */

            $this->executeCreateUpdate($newUser);
        }

        $this->getVerboseFilter()->summary();
    }
}
