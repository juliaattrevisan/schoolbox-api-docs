<?php
/**
 * Synchronise_Synergetic_Mgs_Stragegy_Abstract
 *
 * MGS-specific methods that are provided for each synchronisation Strategy.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

abstract class Synchronise_Synergetic_Mgs_Strategy_Abstract extends Synchronise_Synergetic_Strategy_Abstract {
    protected $verboseFilter = null;

    /* general MGS configurations */
    // TODO: edit fields to match MGS-specific install
    // group IDs
    const STUDENT_GROUP_ID = 3;
    const STAFF_GROUP_ID = 2;
    const PARENT_GROUP_ID = 4;

    // default campuses
    const STAFF_DEFAULT_CAMPUS = 1;
    const STUDENT_DEFAULT_CAMPUS = 1;
    const PARENT_DEFAULT_CAMPUS = 1;

    // schoolbox email enabled
    const STUDENT_LOCAL_EMAIL = 0;
    const STAFF_LOCAL_EMAIL = 0;
    const PARENT_LOCAL_EMAIL = 0;

    // school-based email domains (parents don't have them)
    const STUDENT_EMAIL_SUFFIX = "mgs.vic.edu.au";
    const STAFF_EMAIL_SUFFIX = "mgs.vic.edu.au";

    // ePortfolio enabled by default (parents miss out on these too)
    const STUDENT_EPORTFOLIO = 0;
    const STAFF_EPORTFOLIO = 0;

    /**
     * Constructor
     * Instantiate additional classes
     */
    public function __construct() {
        parent::__construct();

        $this->setVerboseFilter(new Synchronise_Synergetic_Mgs_Verbose());
    }

    /**
     * TODO: modify switch to suit MGS
     * Figure out which campus a staff member is in, based on the code provided
     *
     * @param  $code
     * @return bool|string
     */
    protected function getCampusFromCode($code) {
        switch($code) {
            case 'S':
            case 'M':
            case 'P':
                // return 'main';
                return 1;
                break;
            case 'ELC':
                // return 'elc';
                return 2;
                break;
            default:
                return false;
                break;
        }
    }

    /**
     * TODO: modify switch to suit MGS
     * Figure out which Campus belongs to this Student
     *
     * @param  $year
     * @return bool|string
     */
    protected function getCampusFromYearLevel($year) {
        switch ($year) {
            case '02':
            case '03':
            case '04':
            case '05':
            case '06':
            case '07':
            case '08':
            case '09':
            case '10':
            case '11':
            case '12':
                //return 'main';
                return 1;
                break;
            case '00':
            case '01':
            case '30':
            case '40':
                //return 'elc';
                return 2;
                break;
            default:
                return false;
                break;
        }
    }

    /**
     * TODO: modify switch to suit MGS
     * Figure out the Group ID based on a Student's year level
     *
     * @param  $year
     * @return bool|int
     */
    protected function getGroupIDFromYearLevel($year) {
        switch ($year) {
            case '01':
            case '02':
            case '03':
            case '04':
            case '05':
                return 8;
                break;
            case '06':
            case '07':
            case '08':
            case '09':
                return 7;
                break;
            case '10':
            case '11':
            case '12':
                return 6;
                break;
            case '00':
            case '30':
            case '40':
                return 9;
                break;
            default:
                return false;
                break;
        }
    }


    /**
     * Identical algorithm used to create/update a user
     *
     * @param  $newUser
     * @return void
     */
    protected function executeCreateUpdate($newUser) {

        /* Prepare verbosity
           */
        $this->getVerboseFilter()->setNewUser($newUser);

        /* Does this person have a username? If not, skip them
           */
        if ($this->hasUsername($newUser)) {
            $existing = $this->userExistsBySynergyAndGroup($newUser);

            /* If the current user exists in Schoolbox, update them
                */
            if ($existing) {

                /* Perform action; report what happened
                     */
                if ($this->getPerformUpdate() === true) {
                    $newUser['id'] = $existing['uid'];

                    $this->getUserHandler()->update($newUser);
                    $this->getVerboseFilter()->performUpdate();
                    $this->getVerboseFilter()->incrementUpdated();
                }
                /* Report intentions
                     */
                else {
                    $this->getVerboseFilter()->planUpdate();
                    $this->getVerboseFilter()->incrementUpdated();
                }
            }
            /* Otherwise create them
                */
            else {
                /* Perform action; report what happened
                     */
                if ($this->getPerformUpdate() === true) {
                    $this->getUserHandler()->create($newUser);
                    $this->getVerboseFilter()->performCreation();
                    $this->getVerboseFilter()->incrementCreated();
                }
                /* Report intentions
                     */
                else {
                    $this->getVerboseFilter()->planCreation();
                    $this->getVerboseFilter()->incrementCreated();
                }
            }
        }
        /* Report intentions
           */
        else {
            $this->getVerboseFilter()->skip();
            $this->getVerboseFilter()->incrementSkipped();
        }
    }

    /**
     * Determine if user exists in Schoolbox
     *
     * @param $newUser
     * @return mixed
     */
    protected function userExists($newUser) {
        return $this->getUserHandler()->getBySynergy($newUser['synergy_id']);
    }

    /**
     * Determine if a user exists in Schoolbox in a particular group
     *
     * @param $newUser
     * @return mixed
     */
    protected function userExistsBySynergyAndGroup($newUser) {
        $query = "
			SELECT
				user.id as uid, synergy_id,
				title, firstname, lastname, username,
				local_email, alt_email,
				userCampus.campus, year, house,
				eportfolio_enabled,
				groups.name AS gname, group_id
			FROM user
			LEFT JOIN groups ON user.group_id = groups.id
			LEFT JOIN userCampus ON user.id = userCampus.uid
			WHERE user.synergy_id = '{$newUser['synergy_id']}'
			AND groups.id = '{$newUser['group_id']}'
			ORDER BY userCampus.defaultCampus DESC
		";

        return $this->getUserHandler()->db->getRow($query);
    }

    /**
     * Determine if this person has a username
     *
     * @param  $currentUser
     * @return bool
     */
    protected function hasUsername($currentUser) {
        if (strlen(trim($currentUser['username']))) {
            return true;
        }

        return false;
    }
}