<?php
/**
 * Hydrator for group for Donvale.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Donvale_Hydrator_Group_Student implements Synchronise_Base_IHydrator {

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['group_id'] = 3;

        return $newUser;
    }
}
