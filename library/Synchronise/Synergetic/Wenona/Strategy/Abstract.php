<?php
/**
 * Synchronise_Synergetic_Wenona_Stragegy_Abstract
 *
 * Wenona-specific methods that are provided for each synchronisation Strategy.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

abstract class Synchronise_Synergetic_Wenona_Strategy_Abstract extends Synchronise_Synergetic_Strategy_Abstract {
    protected $verboseFilter = null;

    // do a little house synchro also
    private $houses = array();
    protected $newHouses = array();
    protected $syncHouses = true;

    /* general Wenona configurations */
    // group IDs
    const STUDENT_GROUP_ID = '6,7,8,9,10,11,12,13,14,15,16,17,18';
    const STAFF_GROUP_ID   = 2;
    const ADMIN_GROUP_ID   = 1;
    const PARENT_GROUP_ID  = 4;

    // default campuses
    const STAFF_DEFAULT_CAMPUS   = 1;
    const STUDENT_DEFAULT_CAMPUS = 1;
    const PARENT_DEFAULT_CAMPUS  = 1;

    // schoolbox email enabled
    const STUDENT_LOCAL_EMAIL = 1;
    const STAFF_LOCAL_EMAIL   = 1;
    const PARENT_LOCAL_EMAIL  = 0;

    // school-based email domains (parents don't have them)
    const STUDENT_EMAIL_SUFFIX = "wenona.nsw.edu.au";
    const STAFF_EMAIL_SUFFIX   = "wenona.nsw.edu.au";

    // ePortfolio disabled by default
    // -1 means 'leave it alone', 0 means turn off, 1 means turn on
    const STUDENT_EPORTFOLIO = 1;
    const STAFF_EPORTFOLIO   = -1;

    /**
     * Constructor
     * Instantiate additional classes
     */
    public function __construct() {
        parent::__construct();
        $this->setVerboseFilter(new Synchronise_Synergetic_Wenona_Verbose());
        $result = $this->getUserHandler()->db->getAll('SELECT * FROM house');
        $this->houses = $result ? $result : array();
        $this->syncHouses = count($this->houses) ? false : true;
    }

    /**
     * Figure out which campus a staff member is in, based on the code provided
     *
     * @param  $code
     * @return bool|string
     */
    protected function getCampusFromCode($code) {
        switch($code) {
        case 'J': // junior
    		return array(2);
        case 'M': // middleschool
	    	return array(3);
        case 'S': // senior
		    return array(1);
        case 'W': // whole school
            return array(1, 2, 3);
        }
        return false;
    }

    /**
     * Figure out which Campus belongs to this Student
     *
     * @param  $year
     * @return bool|string
     */
    protected function getCampusFromYearLevel($year) {
        switch ($year) {
	    case '0';
	    case '1';
        case '2':
        case '3':
        case '4':
        case '5':
        case '6':
    		return 2; // junior

        case '7':
        case '8':
        case '9':
            return 3; // middleschool

        case '10':
        case '11':
        case '12':
    		return 1; // senior
        }

        return false;
    }


    protected function getHouseFromCode($house) {

        foreach($this->houses as $h) {
            if($h['house'] == $house) {
                return $h['id'];
            }
        }

        // house not found: if we're syncing, create the house (which will just be the code)
        if($this->syncHouses) {
            if(trim($house)) {
                echo "+  Adding house ($house) to houses list.\n";

                // house not found - add it
                $new = array(
                    'id' => count($this->houses) + 1,
                    'house' => $house
                );
                $this->houses[] = $new;
                $this->newHouses[] = $new;
                return count($this->houses);
            }
        }
        return false;
    }


    /**
     * Figure out the Group ID based on a Student's year level
     *
     * @param  $year
     * @return bool|int
     */
    protected function getGroupIDFromYearLevel($year) {
        switch ($year) {
            case '0' :
                return 12;
            case '01':
                return 13;
            case '02':
                return 14;
            case '03':
                return 15;
            case '04':
                return 16;
            case '05':
                return 17;
            case '06':
                return 18;
            case '07':
                return 6;
            case '08':
                return 7;
            case '09':
                return 8;
            case '10':
                return 9;
            case '11':
                return 10;
            case '12':
                return 11;
        }
        return false;
    }

   protected function getGroupIDFromCategory($category) {
       if($category == 'TEACHER')
		return 1;
	else
		return 2;
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
                /* dont update staff campuses */
                if($newUser['group_id'] == self::STAFF_GROUP_ID || $newUser['group_id'] == self::ADMIN_GROUP_ID){
                    unset($newUser['campus']);
                    unset($newUser['defaultCampus']);
                }

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
			WHERE (user.synergy_id = '{$newUser['synergy_id']}'
			AND groups.id = '{$newUser['group_id']}' ) OR user.username = '{$newUser['username']}'
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

    protected function generatePassword($currentUser) {
        // if parent, and if password is blank

    }
}
