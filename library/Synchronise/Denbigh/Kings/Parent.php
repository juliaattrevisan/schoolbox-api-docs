<?php
/**
 * Synchronise_Denbigh_Kings_Parent
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Denbigh_Kings_Parent extends Synchronise_Denbigh_Kings_Abstract {

    public function generateList() {
        $this->collateList($this->getExternalUserHandler()->getParentUserAccountInfo());
    }

    /**
     * Creates the users and inserts them into the database (unless they already exist)
     */
    public function create() {

        $previousParent = null;

        $this->getVerboseFilter()->intro();

        foreach($this->getList() as $currentParent) {
            $this->executeCreateUpdate($this->generateUserAttributes($currentParent));
        }

        $this->getVerboseFilter()->summary();

    }

    public function generateUserAttributes($currentUser) {
        $newUser = array();

        // set attributes
        $newUser['username']            = $currentUser['denbighID'];
     // $newUser['password']            = 'abc123';
        $newUser['group_id']            = self::PARENT_GROUP_ID;

        $newUser['title']               = $currentUser['title'];
        $newUser['firstname']           = $currentUser['preferred_name'];
        $newUser['lastname']            = $currentUser['surname'];

     // $newuser['email_pass']          = 'abc123';
        $newUser['synergy_id']          = $currentUser['denbighID'];
        $newUser['campus']              = array(self::DEFAULT_CAMPUS_ID);
        $newUser['year']                = null;
        $newUser['house']               = null;

        $newUser['local_email']         = 0;
        $newUser['alt_email']           = $currentUser['email'];
        $newUser['eportfolio_enabled']  = self::EPORTFOLIO_PARENT_ENABLED ? 1 : 0;

        $newUser['su']                  = false; // parents are not super-users
        $newUser['defaultCampus']       = self::DEFAULT_CAMPUS_ID;

        return $newUser;
    }

}
