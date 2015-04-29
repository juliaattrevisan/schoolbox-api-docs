<?php
/**
 * Synchronise_External_Winifred_Abstract
 *
 * Abstract class for generic implementation of database synchronisation.
 * Extending classes must implement core functions.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @stolen_from Matt Day <matt.day@alaress.com.au>
 */
abstract class Synchronise_Denbigh_Winifred_Strategy_Abstract extends Synchronise_Denbigh_Abstract {
    // group IDs
    const STUDENT_GROUP_ID = 3;
    const STAFF_GROUP_ID = 2;
    const PARENT_GROUP_ID = 4;

    // Winifred has two campuses: Frensham (1) and Gib Gate (2), Gib Gate isn't
    // used.
    const DEFAULT_CAMPUS_ID = 1;
    const FRENSHAM_CAMPUS_ID = 1;
    const GIBGATE_CAMPUS_ID = 2;

    // default ePortfolio states
    const EPORTFOLIO_STUDENT_ENABLED = false;
    const EPORTFOLIO_STAFF_ENABLED = false;
    const EPORTFOLIO_PARENT_ENABLED = false;

    protected $verbosityFilter = null;

    public function __construct() {
        parent::__construct();

        $this->setVerboseFilter(new Synchronise_Denbigh_Winifred_Verbose());
    }

    protected function getGroupIDFromYearLevel($yearLevel) {
        // all students are merely students, no distinction between groups
        return $this::STUDENT_GROUP_ID;
    }

    protected function getCampusFromYearLevel($yearLevel) {
        // single campus
        if($yearLevel > 6) {
            return $this::FRENSHAM_CAMPUS_ID;
        }
        else if($yearLevel <= 6 && $yearLevel >= -2) {
            return $this::GIBGATE_CAMPUS_ID;
        }
        else {
            return $this::DEFAULT_CAMPUS_ID;
        }

    }

    protected function getYearFromYearLevel($yearLevel) {
        switch ($yearLevel) {
            case '-2':
                return 'ELC';
                break;
            case '-1':
                return 'ELC';
                break;
            case '0':
                return 'K';
                break;
            default:
                return $yearLevel;
        }
    }

    protected function getHouse($house) {
        $house = HouseMapper::getInstance()->findByColumn('house',$house);
        if ($house->count() > 0) {
            return $house[0]->getId();
        }
        return 0;
    }

    protected function getResidentialHouse($house) {
        $house = HouseResidentialMapper::getInstance()->findByColumn('name',$house);
        if ($house->count() > 0) {
            return $house[0]->getId();
        }
        return 0;
    }

    /**
     * Figure out the user's username from their external database data.
     *
     * For Winifred, it's the username part of their frensham.nsw.edu.au email
     * address (if they have one!)
     *
     * @param  array $user
     *
     * @return string|bool The user's username, or false if not set.
     */
    protected function getUsernameFromCurrentUser($user) {
        if (preg_match('/^(.+?)@frensham\.nsw\.edu\.au$/', $user['email'], $matches)) {
            return $matches[1];
        }
        return false;
    }

    /**
     * Create or update a user in Schoolbox db, depending on whether user already exists
     * @param $newUser - an array of user attributes
     * @return void
     */
    protected function executeCreateUpdate($newUser) {

        $this->getVerboseFilter()->setNewUser($newUser);

        // bug out if denbigh user doesn't have a username
        if(!trim($newUser['username'])) {
            $this->getVerboseFilter()->skip();
            $this->getVerboseFilter()->incrementSkipped();
            return;
        }

        // does the user exist in schoolbox?
        if($this->userExists($newUser)) {
            // if yes, update details
            $existingUser = $this->getUserHandler()->getByUsername($newUser['username']);
            $newUser['id'] = $existingUser['uid'];
            if($this->getPerformUpdate()) {
                // actually do it
                $this->getUserHandler()->update($newUser);
                $this->getVerboseFilter()->performUpdate();
                $this->getVerboseFilter()->incrementUpdated();

            } else {
                // test only
                $this->getVerboseFilter()->planUpdate();
                $this->getVerboseFilter()->incrementUpdated();

            }

        } else {
            // if no, create from scratch
            if($this->getPerformUpdate()) {
                // actually do it
              $this->getUserHandler()->create($newUser);
                $this->getVerboseFilter()->performCreation();
                $this->getVerboseFilter()->incrementCreated();

            } else {
                // test only
                $this->getVerboseFilter()->planCreation();
                $this->getVerboseFilter()->incrementCreated();

            }
        }
    }


    /**
     * @param $username
     * @return bool
     */
    protected function userExists($user) {
        // just !=, not !==
        return $this->getUserHandler()->getByUsername($user['username']) != NULL;
    }

    // extending classes must implement this function
    public abstract function generateUserAttributes($currentUser);


    /* Getters and Setters */

    public function setVerboseFilter($filter) {
        $this->verbosityFilter = $filter;
    }

    public function getVerboseFilter() {
        return $this->verbosityFilter;
    }
}
