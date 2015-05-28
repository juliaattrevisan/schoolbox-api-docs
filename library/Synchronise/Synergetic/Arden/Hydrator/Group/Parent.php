<?php

/**
 * Hydrator for group for Arden.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Arden_Hydrator_Group_Parent implements Synchronise_Base_IHydrator
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
     * Groups for Scs are based on year level
     *
     * @param  array $externalUser
     * @return int
     */
    public function getGroupIDFromUser($externalUser)
    {
        /* All staff are in Group 1
         */
        return 4;
    }
}
