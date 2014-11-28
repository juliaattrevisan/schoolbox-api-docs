<?php
/**
 * Synchronise_Synergetic_StPauls_Strategy_Parent
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_StPauls_Strategy_Parent extends Synchronise_Synergetic_StPauls_Strategy_Abstract {
    protected $strategyTitle = "Update Schoolbox Parent Details Synchronisation";


    /**
     * Setup
     */
    protected function setup() {
        $this->setVerboseFilter(new Synchronise_Synergetic_StPauls_Verbose_Parent());
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

        $parent = $this->getExternalUserHandler()->getParent($currentUser['StudentContactID']);

        // User details
        $newUser['username']   = $currentUser['StudentContactID'];
        $newUser['synergy_id'] = $currentUser['StudentContactID'];
        $newUser['title']      = $currentUser['StudentContactTitle'];
        $newUser['firstname']  = $currentUser['StudentContactPreferred'];
        $newUser['lastname']   = $currentUser['StudentContactSurname'];
        $newUser['alt_email']  = $currentUser['StudentContactDefaultEmail'];

        // get campuses of attached children
        $newUser['campus']        = array($this->getCampusFromCurrentUser($currentUser));
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

        $parent = $this->getExternalUserHandler()->getParent($currentUser['StudentContactSpouseID']);

        // User details
        $newUser['username']   = $currentUser['StudentContactSpouseID'];
        $newUser['synergy_id'] = $currentUser['StudentContactSpouseID'];
        $newUser['title']      = $currentUser['StudentContactSpouseTitle'];
        $newUser['firstname']  = $currentUser['StudentContactSpousePreferred'];
        $newUser['lastname']   = $currentUser['StudentContactSpouseSurname'];
        $newUser['alt_email']  = $currentUser['StudentContactSpouseDefaultEmail'];

        // get campuses of kids
        $newUser['campus']        = array($this->getCampusFromCurrentUser($currentUser));
        $newUser['defaultCampus'] = $newUser['campus'][0];
        $newUser['group_id']      = self::PARENT_GROUP_ID;
        $newUser['su']            = false;

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

        //
        // Pass 1: get a unique list of users
        $parents = array();
        foreach ($this->getList() as $currentUser) {
            //
            // Get user and their spouse.
            $users = array(
                $this->setupUserDetails($currentUser),
                $this->setupSpouseDetails($currentUser),
            );
            foreach ($users as $newUser) {
                //
                // Add each user to the list of unique users, merging the campus
                // list into the new one.
                $user_id = $newUser['synergy_id'];
                if (!isset($parents[$user_id])) {
                    $parents[$user_id] = $newUser;
                }
                //
                // Overwrite default campus
                $parents[$user_id]['defaultCampus'] = $newUser['campus'];
                $parents[$user_id]['campus'] = array_unique(array_merge(
                        $parents[$user_id]['campus'],
                        $newUser['campus']
                    ));
            }
        }

        //
        // Pass 2: insert/update into list of users
        foreach ($parents as $parent) {
            $this->executeCreateUpdate($parent);
        }

        $this->getVerboseFilter()->summary();
    }
}
