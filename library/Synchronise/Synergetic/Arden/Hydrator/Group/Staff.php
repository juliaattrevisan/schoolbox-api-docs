<?php

/**
 * Hydrator for group for Arden.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Arden_Hydrator_Group_Staff implements Synchronise_Base_IHydrator
{

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser['group_id'] = $this->getGroupIDFromUser($externalUser);

        return $newUser;
    }

    /**
     * Roles are based on campus
     *
     * @param  array $externalUser
     * @return int
     */
    public function getGroupIDFromUser($externalUser)
    {
        switch ($externalUser['StaffCampus']) {
            case 'E':
                return 1;

            case 'B':
                return 6;

            default:
                return 6;
        }
    }
}
