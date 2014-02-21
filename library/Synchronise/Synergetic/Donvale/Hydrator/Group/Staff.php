<?php
/**
 * Hydrator for group for Donvale staff.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Donvale_Hydrator_Group_Staff implements Synchronise_Base_IHydrator {

    /**
     * Hydrates groups according to following:
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['group_id'] = 1;

        return $newUser;
    }
}
