<?php
/**
 * Synchronise_Synergetic_Mggs_Strategy_Abstract
 *
 * Mggs-specific methods that are provided for each synchronisation Strategy.
 *
 * You can create custom subclasses of Synchronise_Synergetic_Strategy_Abstract
 * to provide unique methods to your synchronisation strategies.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Synchronise_Synergetic_Mggs_Strategy_Abstract extends Synchronise_Synergetic_Strategy_Abstract
{
    protected $verboseFilter = null;
    const PARENT_GROUP_ID = 4;
	const STAFF_GROUP_ID = 2;
	const ADMIN_GROUP_ID = 1;

    /**
     * Setup
     */
    protected function setup()
    {
        $this->setVerboseFilter(new Synchronise_Synergetic_Mggs_Verbose());
    }

    /**
     * Identical algorithm used to create/update a user
     *
     * @param  $newUser
     * @return void
     */
    protected function executeCreateUpdate($newUser)
    {

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
                } /* Report intentions
				 */
                else {
                    $this->getVerboseFilter()->planUpdate();
                    $this->getVerboseFilter()->incrementUpdated();
                }
            } /* Otherwise create them
			 */
            else {
                /* Perform action; report what happened
                     */
                if ($this->getPerformUpdate() === true) {
                    // Do not create parents
                    if ($newUser['group_id'] != self::PARENT_GROUP_ID) {
                        $this->getUserHandler()->create($newUser);

                        $this->getVerboseFilter()->performCreation();
                        $this->getVerboseFilter()->incrementCreated();
                    }
                } /* Report intentions
				 */
                else {
                    // Do not create parents
                    if ($newUser['group_id'] != self::PARENT_GROUP_ID) {
                        $this->getVerboseFilter()->planCreation();
                        $this->getVerboseFilter()->incrementCreated();
                    }
                }
            }
        } /* Report intentions
		 */
        else {
            $this->getVerboseFilter()->skip();
            $this->getVerboseFilter()->incrementSkipped();
        }
    }

    /**
     * Figure out which campus a student member is in, based on the code provided
     *
     * @param  $code
     * @return bool|string
     */
    protected function getCampusFromCode($code)
    {
        switch ($code) {
            case 'E':
                return 1;
            case 'J':
                return 2;
            case 'S':
                return 3;
            default:
                // return 'E';
                return 1;
        }
    }

    /**
     * Figure out which Campus belongs to this Student
     *
     * @param  $year
     * @return bool|string
     */
    protected function getCampusFromYearLevel($year)
    {
        switch ($year) {
            case '0';
            case '1';
            case '2':
            case '3':
            case '4':
                return 2;
            case '5':
            case '6':
            case '7':
            case '8':
            case '9':
            case '10':
            case '11':
            case '12':
                return 3;
            case '40':
            case '30':
            case '50':
                return 1;
            default:
                return false;
                break;
        }
    }

    /**
     * Figure out the Group ID based on a Student's year level
     *
     * @param  $year
     * @return bool|int
     */
    protected function getGroupIDFromYearLevel($year)
    {
        switch ($year) {
            case '0':
            case '1':
            case '2':
            case '3':
            case '4':
            case '30':
            case '40':
                return 6;
            case '5':
            case '6':
            case '7':
            case '8':
                return 5;
            case '9':
            case '10':
            case '11':
            case '12':
                return 3;
            default:
                return false;
        }
    }

    /**
     * Determine if this person has a username
     *
     * @param  $currentUser
     * @return bool
     */
    protected function hasUsername($currentUser)
    {
        if (strlen(trim($currentUser['username']))) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user exists in Schoolbox
     *
     * @param $newUser
     * @return mixed
     */
    protected function userExists($newUser)
    {
        return $this->getUserHandler()->getBySynergy($newUser['synergy_id']);
    }

    /**
     * Determine if a user exists in Schoolbox in a particular group
     *
     * @param $newUser
     * @return mixed
     */
    protected function userExistsBySynergyAndGroup($newUser)
    {
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
                        WHERE user.synergy_id = '{$newUser['synergy_id']}'";

        //Only lock user to group if parent account
        if ($newUser['group_id'] == self::PARENT_GROUP_ID)
            $query .= " AND groups.id = '{$newUser['group_id']}'";


        $query .= " ORDER BY userCampus.defaultCampus DESC";

        return $this->getUserHandler()->db->getRow($query);
    }
}