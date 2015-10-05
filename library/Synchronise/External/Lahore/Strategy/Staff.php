<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Lahore_Strategy_Staff extends Synchronise_External_Lahore_Strategy_Abstract {

    /**
     * Process user
     */
    public function createIfExists() {
        $this->getVerboseFilter()->intro();

        if(is_array($this->entityData)) {
            foreach($this->entityData as $currentUser) {
                // Generate details about the new user
                $newUser = $this->setupUserDetails($currentUser);

                $this->executeCreateUpdate($newUser);
            }
            $this->getVerboseFilter()->summary();
        }
    }

    /**
     * Set up the new user's details
     *
     * @param  $currentUser
     * @return array
     */
    public function setupUserDetails($currentUser) {
        $newUser = array();

//        the data may well have `/` separators in it, which need to be handled
//        foreach($currentUser as &$field) {
//            $expl = explode('/',$field);
//            $field = trim($expl[0]);
//        }

        // Login details
        $newUser['username'] = trim($currentUser['networkLogin']);

        // User details
        $newUser['title'] = $currentUser['title'];
        $newUser['firstname'] = trim($currentUser['firstName']);
        $newUser['lastname'] = trim($currentUser['lastName']);
        $newUser['synergy_id'] = $currentUser['staffID'];
        $newUser['alt_email'] = $currentUser['localEmail'];
        $newUser['local_email'] = 1;

        // Campus config: needs to map to a campus id
        $newUser['defaultCampus'] = 1;
        $newUser['campus'] = array(1);

        // Staff Group ID
        $newUser['group_id'] = self::STAFF_GROUP_ID;

        // This person is not a superuser
        //$newUser['su'] = false;

        return $newUser;
    }



}
