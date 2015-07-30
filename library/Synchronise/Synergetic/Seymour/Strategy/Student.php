<?php

/**
 * Synchronise_Synergetic_Seymour_Strategy_Student
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * Adds Students in Synergetic to the Schoolbox User table.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_Seymour_Strategy_Student extends Synchronise_Synergetic_Seymour_Strategy_Abstract
{
    protected $strategyTitle = "Update Schoolbox Student Details Synchronisation";

    /**
     * Setup
     */
    protected function setup()
    {
        $this->setVerboseFilter(new Synchronise_Synergetic_Seymour_Verbose_Student());
    }

    /**
     * Get a list of users' Synergy IDs to sync
     *
     * @return void
     */
    public function generateList()
    {
        $this->collateList($this->getExternalUserHandler()->getStudentUserAccountInfo());
    }

    /**
     * Set up the new user's details
     *
     * @param  $currentUser
     *
     * @return array
     */
    public function setupUserDetails($currentUser)
    {
        $newUser = array();
        /* Login details
         * We don't know how the password is given to us
         */
        $newUser['username'] = $currentUser['NetworkLogin'];
        /* User details
         */
        $newUser['title']              = '';
        $newUser['firstname']          = $currentUser['StudentPreferred'];
        $newUser['lastname']           = $currentUser['StudentSurname'];
        $newUser['alt_email']          = $currentUser['StudentOccupEmail'];
        $newUser['synergy_id']         = $currentUser['StudentID'];
        $newUser['local_email']        = 0;
        $newUser['eportfolio_enabled'] = 1;
        $newUser['year']               = $currentUser['StudentYearLevel'];
        // House config: needs to map to a house id
        $newUser['house'] = $this->getHouseFromCode($currentUser['StudentHouse']);
        /* Campus config: needs to map to a campus id
         */
        $newUser['defaultCampus'] = $this->getCampusFromCode($currentUser['StudentCampus']);
        $newUser['campus']        = array($this->getCampusFromCode($currentUser['StudentCampus']));
        /* Student Group ID
         */
        $newUser['group_id'] = $this->getGroupIDFromYearLevel($currentUser['StudentYearLevel']);
        // Residential house - Seymour has only one
        $newUser['residential_house_id'] = $currentUser['StudentBoarder'] === 'B' ? 1 : 0;
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
    public function createIfExists()
    {
        $this->getVerboseFilter()->intro();
        foreach ($this->getList() as $currentUser) {
            /* Generate details about the new user
             */
            $this->executeCreateUpdate($this->setupUserDetails($currentUser));
        }
        $this->getVerboseFilter()->summary();
    }
}
