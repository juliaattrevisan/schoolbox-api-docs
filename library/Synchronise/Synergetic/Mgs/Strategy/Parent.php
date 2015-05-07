<?php
/**
 * Synchronise_Synergetic_Mgs_Strategy_Parent
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>
 */

class Synchronise_Synergetic_Mgs_Strategy_Parent extends Synchronise_Synergetic_Mgs_Strategy_Abstract {

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

        // Login details

        // User details
        $newUser['synergy_id'] = $currentUser['StudentContactID'];
        $newUser['title'] = $currentUser['StudentContactTitle'];
        $newUser['firstname'] = $currentUser['StudentContactPreferred'];
        $newUser['lastname'] = $currentUser['StudentContactSurname'];
        $newUser['alt_email'] = $currentUser['StudentContactDefaultEmail'];
        // get campuses of attached children
        $newUser['campus'] = array($this->getCampusFromYearLevel($currentUser['StudentYearLevel']));
        $newUser['defaultCampus'] = $newUser['campus'][0];
        $newUser['group_id'] = self::PARENT_GROUP_ID;

        // parents are not superusers
        $newUser['su'] = false;

        return $newUser;
    }

    /**
     * Set up the new user's spouse's details
     *
     * @param $currentUser
     * @return array
     */
    public function setupSpouseDetails($currentUser) {
        $spouse = array();

        // Login details

        // User details
        $spouse['synergy_id'] = $currentUser['StudentContactSpouseID'];
        $spouse['title'] = $currentUser['StudentContactSpouseTitle'];
        $spouse['firstname'] = $currentUser['StudentContactSpousePreferred'];
        $spouse['lastname'] = $currentUser['StudentContactSpouseSurname'];
        $spouse['alt_email'] = $currentUser['StudentContactSpouseDefaultEmail'];
        // get campuses of kids
        $spouse['campus'] = array($this->getCampusFromYearLevel($currentUser['StudentYearLevel']));
        $spouse['defaultCampus'] = $spouse['campus'][0];
        $spouse['group_id'] = self::PARENT_GROUP_ID;
        // parents are not superusers
        //$spouse['su'] = false;

        return $spouse;
    }


    /**
     * If the users exist in Synergy but not in Schoolbox, create an account for
     * them
     *
     * @return void
     */
    public function createIfExists() {
        $previousUser = null;
        $compiledCampus = array();

        $this->getVerboseFilter()->intro();

        foreach($this->getList() as $currentUser) {
            $newUser = $this->setupUserDetails($currentUser);
            $newSpouse = $this->setupSpouseDetails($currentUser);

            /* First iteration
                */
            if ($previousUser == null) {
                if (is_array($newUser['campus'])) {
                    $compiledCampus = $newUser['campus'];
                }

                $previousUser = $newUser;
                $previousSpouse = $newSpouse;
            }
            /* Previous was different person to Current
                */
            else if ($previousUser['synergy_id'] != $newUser['synergy_id']) {
                $previousUser['campus'] = $compiledCampus;
                $previousSpouse['campus'] = $compiledCampus;

                /* Uniquify campus list
                     */
                $previousUser['campus'] = array_unique($previousUser['campus']);
                $previousSpouse['campus'] = array_unique($previousSpouse['campus']);

                /* Update previous user
                     */
                $this->executeCreateUpdate($previousUser);
                $this->executeCreateUpdate($previousSpouse);

                /* Recompile
                     */
                $compiledCampus = $newUser['campus'];

                $previousUser = $newUser;
                $previousSpouse = $newSpouse;
            }
            /* They are the same person
                */
            else {
                $compiledCampus = array_merge($compiledCampus, $newUser['campus']);
            }
        }

        $this->getVerboseFilter()->summary();
    }
}
