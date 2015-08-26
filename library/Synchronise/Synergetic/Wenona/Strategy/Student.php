<?php

/**
 *
 * Synchronise_Synergetic_Wenona_Strategy_Student
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>
 */
class Synchronise_Synergetic_Wenona_Strategy_Student extends Synchronise_Synergetic_Wenona_Strategy_Abstract
{

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
     * @return array
     */
    public function setupUserDetails($currentUser)
    {
        $newUser = array();

        // login details
        $newUser['username'] = $currentUser['StudentID'];
        // $newUser['password'] = '1234';

        // user attributes
        $newUser['title'] = '';
        $newUser['firstname'] = $currentUser['StudentPreferred'];
        $newUser['lastname'] = $currentUser['StudentSurname'];
        //$newUser['alt_email']     = '';
        $newUser['synergy_id'] = $currentUser['StudentID'];
        $newUser['local_email'] = self::STUDENT_LOCAL_EMAIL;
        $newUser['year'] = $currentUser['StudentYearLevel'];
        $newUser['house'] = $this->getHouseFromCode($currentUser['StudentHouse']);
        $newUser['defaultCampus'] = $this->getCampusFromYearLevel($currentUser['StudentYearLevel']);
        $newUser['campus'] = array($this->getCampusFromYearLevel($currentUser['StudentYearLevel']));
        $newUser['group_id'] = $this->getGroupIDFromYearLevel($currentUser['StudentYearLevel']);

        if (self::STUDENT_EPORTFOLIO != -1) {
            $newUser['eportfolio_enabled'] = self::STUDENT_EPORTFOLIO;
        }
        
        // students aren't superusers
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
            // generate user details
            $this->executeCreateUpdate($this->setupUserDetails($currentUser));
        }

        // update houses
        if ($this->syncHouses && count($this->newHouses)) {
            $query = "INSERT INTO `house` (`id`, `house`) VALUES ";
            $inserts = array();
            foreach ($this->newHouses as $house) {
                $inserts[] = "('{$house['id']}', '{$house['house']}')";
            }
            $query .= implode(', ', $inserts);

            if ($this->getPerformUpdate()) {
                $this->getUserHandler()->db->query($query);
            } else {
                echo "+  Planning SQL query:\n   $query";
            }
        }

        $this->getVerboseFilter()->summary();
    }
}
