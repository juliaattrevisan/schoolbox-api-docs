<?php

/**
 * Hydrator for group for Arden.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Arden_Hydrator_Group_Student implements Synchronise_Base_IHydrator
{

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        switch ($externalUser['StudentCampus']) {
            case 'B':
                $newUser['group_id'] = 2;
                break;

            case 'E':
                $newUser['group_id'] = 3;
                break;

            default:
                $newUser['group_id'] = null;
                break;
        }

        return $newUser;
    }
}
