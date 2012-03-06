<?php
/**
 * Synchronise_Denbigh_Kings_Staff
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Denbigh_Kings_Staff extends Synchronise_Denbigh_Kings_Abstract {

    public function generateList() {
        $this->collateList($this->getExternalUserHandler()->getStaffUserAccountInfo());
    }

    /**
     * Creates the users and inserts them into the database (unless they already exist)
     */
    public function create() {
        $this->getVerboseFilter()->intro();
        foreach($this->getList() as $currentUser) {
            $this->executeCreateUpdate($this->generateUserAttributes($currentUser));
        }
        $this->getVerboseFilter()->summary();
    }

    public function generateUserAttributes($currentUser) {
        $newUser = array();

        // set attributes
        $newUser['username']            = $currentUser['staffCode'];
     // $newUser['password']            = 'abc123';
        $newUser['group_id']            = self::STAFF_GROUP_ID;
        $newUser['title']               = $currentUser['title'];
        $newUser['firstname']           = $currentUser['preferred_name'];
        $newUser['lastname']            = $currentUser['surname'];
     // $newuser['email_pass']          = 'abc123';
        $newUser['synergy_id']          = $currentUser['denbighID']; // change this to denbighID, requires SQL modification
        $newUser['campus']              = self::DEFAULT_CAMPUS_ID;
        $newUser['year']                = '';
        $newUser['house']               = '';
        $newUser['alt_email']           = $currentUser['email'] == NULL ? strtolower($currentUser['staffCode'] . "@kings.edu.au")
                                                                        : $currentUser['email'];
        $newUser['local_email']         = 0;
        $newUser['su']                  = 0;
        $newUser['defaultCampus']       = self::DEFAULT_CAMPUS_ID;
        $newUser['eportfolio_enabled']  = self::EPORTFOLIO_STAFF_ENABLED ? 1 : 0;

        return $newUser;
    }
}
