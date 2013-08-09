<?php
/**
 * Synchronise_External_Kings_Abstract
 *
 * Abstract class for generic implementation of database synchronisation.
 * Extending classes must implement core functions.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Synchronise_Denbigh_Kings_Abstract extends Synchronise_Denbigh_Abstract {
    // group IDs
    const STUDENT_GROUP_ID = 3;
    const STAFF_GROUP_ID = 2;
    const PARENT_GROUP_ID = 4;

    // Kings only has the one campus
    const DEFAULT_CAMPUS_ID = 1;

    // default ePortfolio states
    const EPORTFOLIO_STUDENT_ENABLED = true;
    const EPORTFOLIO_STAFF_ENABLED = true;
    const EPORTFOLIO_PARENT_ENABLED = false;

    protected $verbosityFilter = null;

    public function __construct() {
        parent::__construct();

        $this->setVerboseFilter(new Synchronise_Denbigh_Kings_Verbose());
    }

    protected function getGroupIDFromYearLevel($yearLevel) {
        //
        // Students in Primary school: group 5, otherwise group 3.
        if ($yearLevel < 7) {
            return 5;
        }
        return $this::STUDENT_GROUP_ID;
    }

    protected function getCampusFromYearLevel($yearLevel) {
        //
        // Students in Primary school: campus 3, otherwise campus 1.
        if ($yearLevel < 7) {
            return 3;
        }
        return $this::DEFAULT_CAMPUS_ID;
    }

    protected function getHouse($house) {
        $house = HouseMapper::getInstance()->findByColumn('house',$house);
        if ($house->count() > 0)
            return $house->first()->getId();
    }

    protected function getResidentialHouse($house) {
        $house = HouseResidentialMapper::getInstance()->findByColumn('name',$house);
        if ($house->count() > 0)
            return $house->first()->getId();
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