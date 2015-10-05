<?php
/**
 * Synchronise_Synergetic_Moriah_Strategy_Abstract
 *
 * Moriah-specific methods that are provided for each synchronisation Strategy.
 *
 * You can create custom subclasses of Synchronise_Synergetic_Moriah_Strategy_Abstract
 * to provide unique methods to your synchronisation strategies.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Synchronise_Synergetic_Moriah_Strategy_Abstract extends Synchronise_Synergetic_Strategy_Abstract implements Synchronise_Synergetic_Moriah_Strategy_Interface
{
    protected $verboseFilter = null;

    /*  id campus name

         5 Primary School K-2
        11 Primary School 3-5
         9 Middle School
         4 High School

         3 Early Childhood Centre
         6 Dover Rd Preschool
         7 Glenayr Ave Preschool
         8 Randwick Preschool
        10 Preschool

        12 Mt Zion
    */


//    const CG =  1; // College General;
//    const S  =  2; // Secondary School;
    const EC =  3; // Early Childhood Centre;
    const HS =  4; // High School;
    const IS =  5; // Primary School K-2;
    const MD =  6; // Dover Rd Preschool;
    const MG =  7; // Glenayr Ave Preschool;
    const MR =  8; // Randwick Preschool;
    //const MS =  9; // Middle School;
    const P  = 10; // Preschool;
    const PS = 11; // Primary School 3-5;
    const Z  = 12; // Mt Zion;

    /**
    * Construct
    *
    * Instantiate additional classes
    */
    public function __construct()
    {
        parent::__construct();

        $this->setVerboseFilter(new Synchronise_Synergetic_Moriah_Verbose());
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
            case '01':
                return 7;
            case '02':
                return 8;
            case '03':
                return 9;
            case '04':
                return 10;
            case '05':
                return 11;
            case '06':
                return 12;
            case '07':
                return 13;
            case '08':
                return 14;
            case '09':
                return 15;
            case '10':
                return 16;
            case '11':
                return 17;
            case '12':
                return 18;
            case '00':
            case '20':
            case '30':
            case '40':
            case '50':
                return 6;
            default:
                return false;
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
        /*  id campus name

             5 Primary School K-2
            11 Primary School 3-5
             9 Middle School
             4 High School

             3 Early Childhood Centre
             6 Dover Rd Preschool
             7 Glenayr Ave Preschool
             8 Randwick Preschool
            10 Preschool

            12 Mt Zion
        */

        switch ($year) {
            case '00':
            case '01':
            case '02':
                return self::IS;
            case '03':
            case '04':
            case '05':
            case '06':
                return self::PS;
            case '07':
            case '08':
            case '09':
            case '10':
            case '11':
            case '12':
                return self::HS;
            case '20':
            case '30':
            case '40':
            case '50':
                return self::P; // TODO check this: alternatively, can map preschool to nothing and manually apply
            default:
                return false;
        }
    }

    /**
    * Figure out which campus a staff member is in, based on the code provided
    *
    * @param  $code
    * @return bool|string
    */
    protected function getCampusFromCode($code)
    {
        return defined('self::'.$code) ? constant(sprintf("%s::%s", 'self', $code)) : self::HS;
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
            $existing = $this->userExists($newUser);

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
    protected function hasUsername($currentUser)
    {
        if (strlen(trim($currentUser['username']))) {
            return true;
        }

        return false;
    }
}
