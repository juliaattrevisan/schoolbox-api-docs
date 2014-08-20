<?php
/**
 * Hydrator for group for Loyola staff.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Loyola_Hydrator_Group_Staff implements Synchronise_Base_IHydrator
{
    /**
     * Hydrates groups according to following:
     *
     * * T, E, M: Teachers (2)
     * * O: Non-Teaching (18)
     */
    public function hydrate($externalUser, $newUser)
    {
        $groupId = null;
        switch ($externalUser['StaffCategory']) {
            case 'T':
            case 'E':
            case 'M':
                $groupId = 2;
                break;
            case 'O':
                $groupId = 18;
                break;
            default:
                throw new Exception('student\'s year level not from 7-12');
        }
        $newUser['group_id'] = $groupId;
        return $newUser;
    }
}
