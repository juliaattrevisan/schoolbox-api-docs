<?php
/**
 * Student
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Synchronise_Synergetic_Arden_Hydrator_Year_Student implements Synchronise_Base_IHydrator {

    /**
     * Special year values for certain students
     *
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        switch ($externalUser['StudentYearLevel']) {
            case -1:
                $newUser['year'] = 'PK';
                break;

            case 0:
                $newUser['year'] = 'K';
                break;

            default: break;
        }

        return $newUser;
    }
} 