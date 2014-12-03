<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Synchronise_Synergetic_Meggs_Hydrator_Staff_Eportfolio implements Synchronise_Base_IHydrator {

    /**
     * This method relies on the Role and Campus already being set
     *
     * Converts data from the format from external database to the format used
     * in Schoolbox.
     *
     * This method should modify and return $newUser.
     *
     * @param  array $externalUser A result from the external database.
     * @param  array $newUser      The current result that will be inserted into
     *                             Schoolbox.
     *                             This result could have come from a hydrate()
     *                             call on another class that implements this
     *                             interface.
     * @return array The result to insert into Schoolbox,
     */
    public function hydrate($externalUser, $newUser)
    {
        $isTeacher = $newUser['group_id'] == Synchronise_Synergetic_Meggs_Hydrator_Staff_Role::ROLE_TEACHERS;
        $isJunior  = in_array(Synchronise_Synergetic_Meggs_Hydrator_Student_Campus::CAMPUS_JNR, $newUser['campus']);

        // only junior teaching staff have ePortfolios
        $newUser['eportfolio_enabled'] = $isTeacher && $isJunior;

        return $newUser;
    }

}