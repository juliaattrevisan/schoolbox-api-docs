<?php

/**
 * StPauls-specific methods that are provided for each synchronisation Strategy.
 *
 * You can create custom subclasses of Synchronise_Synergetic_Strategy_Abstract to
 * provide unique methods to your synchronisation strategies.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Synchronise_Synergetic_StPauls_Strategy_Abstract extends Synchronise_Synergetic_Strategy_Abstract
{
    protected $verboseFilter = null;
    protected $houseMap = array();

    const PARENT_GROUP_ID = 4;

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
                    $this->getUserHandler()->create($newUser);
                    $this->getVerboseFilter()->performCreation();
                    $this->getVerboseFilter()->incrementCreated();
                } /* Report intentions
				 */
                else {
                    $this->getVerboseFilter()->planCreation();
                    $this->getVerboseFilter()->incrementCreated();
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
            case 'J':
                return 2;
            case 'S':
            default:
                return 1;
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
            case '30':
            case '40':
            case '0':
                // Kinder and prep
                return 20;
            case '1':
                return 19;
            case '2':
                return 21;
            case '3':
                return 8;
            case '4':
                return 9;
            case '5':
                return 10;
            case '6':
                return 11;
            case '7':
                return 6;
            case '8':
                return 13;
            case '9':
                return 14;
            case '10':
                return 15;
            case '11':
                return 16;
            case '12':
                return 17;
            case '13':
                return 15;
            default:
                return false;
        }
    }


    /**
     * Figure out the House ID based on a Student's synergetic house
     *
     * @param  $code
     * @return bool|int
     */

    protected function getHouseFromCode($code)
    {
        if (array_key_exists($code, $this->houseMap)) {
            return $this->houseMap[$code];
        }
        return false;
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
     * Calculate Schoolbox campus from Synergetic campus.
     *
     * * 1: Senior (S)
     * * 2: Junior (J)
     *
     * @param $campus
     *
     * @return bool|int
     */
    protected function getCampusFromSynergeticCampus($campus) {
        switch ($campus) {
            case 'S':
                return 1;
            case 'J':
                return 2;
            default:
                return false;
        }
    }

    /**
     * Figure out which Campus belongs to this Student
     *
     * @param  $user
     * @return bool|string
     */
    protected function getCampusFromCurrentUser($user) {
        return $this->getCampusFromSynergeticCampus($user['StudentCampus']);
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
