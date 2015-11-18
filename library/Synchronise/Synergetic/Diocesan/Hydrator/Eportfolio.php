<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Synchronise\Synergetic\Diocesan\Hydrator;


use Synchronise_Base_IHydrator;

class Eportfolio implements Synchronise_Base_IHydrator
{

    /**
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
        $enable = array(
            Student::ROLE_YR_3,
            Student::ROLE_YR_4,
            Student::ROLE_YR_5,
            Student::ROLE_YR_6,
            Student::ROLE_YR_7,
            Student::ROLE_YR_8,
            Student::ROLE_YR_9
        );

        $newUser['eportfolio_enabled'] = in_array($newUser['group_id'], $enable);

        return $newUser;
    }

}