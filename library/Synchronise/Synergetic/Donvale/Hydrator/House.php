<?php
/**
 * Hydrator for house for Donvale users.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Donvale_Hydrator_House implements Synchronise_Base_IHydrator {

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['house'] = $this->getHouseFromUser($externalUser);

        return $newUser;
    }

    /**
     * Gets house according to the following map:
     *
	 * C: Cook
	 * F: Flinders
	 * H: Hume
	 * S: Sturt
     *
     * @param  array $externalUser
     * @return int
     */
    public function getHouseFromUser($externalUser) {
        switch (strtoupper(trim($externalUser['StudentHouse']))) {
            case 'C':
                return 1;
            case 'F':
                return 2;
            case 'H':
                return 3;
            case 'S':
                return 4;
            default:
				return 0;
        }
    }
}
