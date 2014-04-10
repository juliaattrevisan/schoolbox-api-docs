<?php
/**
 * Hydrator for house for Scs users.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Scs_Hydrator_House implements Synchronise_Base_IHydrator {

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
     * @param  array $externalUser
     * @return int
     */
    public function getHouseFromUser($externalUser) {
        switch (strtoupper(trim($externalUser['StudentHouse']))) {
            case 'BB':
                return 20;
            case 'BE':
                return 24;
            case 'BL':
                return 25;
            case 'D':
                return 21;
            case 'H':
                return 26;
            case 'HK':
                return 22;
            case 'K':
                return 27;
            case 'L':
                return 28;
            case 'LT':
                return 23;
			case 'T':
				return 29;
            default:
                break;
        }
        return 0;
    }
}
