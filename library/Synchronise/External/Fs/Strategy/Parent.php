<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Fs_Strategy_Parent extends Synchronise_External_Fs_Strategy_Abstract {

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

        // Login details
        $newUser['username'] = trim($currentUser['networkLogin']);

        // User details
        $newUser['title'] = $currentUser['title'];
        $newUser['firstname'] = trim($currentUser['firstName']);
        $newUser['lastname'] = trim($currentUser['lastName']);
        $newUser['synergy_id'] = $currentUser['parentID'];
        $newUser['alt_email'] = $currentUser['externalEmail'];
        $newUser['password'] = $currentUser['password'];
        $newUser['local_email'] = 0;

        // there is only one campus
        $newUser['defaultCampus'] = 1;
        $newUser['campus'] = array(1);

        // Staff Group ID
        $newUser['group_id'] = self::PARENT_GROUP_ID;

        // This person is not a superuser
        //$newUser['su'] = false;

        return $newUser;
    }

}
