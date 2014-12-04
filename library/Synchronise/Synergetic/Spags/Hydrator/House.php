<?php
/**
 * Hydrator for house for Spags users.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Spags_Hydrator_House implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['house'] = $this->getHouseFromUser($externalUser);
        return $newUser;
    }

    /**
     * Gets house according to the following map:
     * 'BU' 'Burgundy'
     * 'BUR' 'Burgundy'
     * 'CH' 'Chisholm'
     * 'EM' 'Emerald'
     * 'FR' 'Franklin'
     * 'GI' 'Gilmore'
     * 'GO' 'Gold'
     * 'LA' 'Lalor'
     * 'M' 'M'
     * 'MO' 'Monash'
     * 'NA' 'Navy'
     * 'NAV' 'Navy'
     * 'PA' 'Paterson'
     * 'S' 'S'
     *
     * @param  array $externalUser
     * @return int
     */
    public function getHouseFromUser($externalUser) {
        switch (strtoupper(trim($externalUser['StudentHouse']))) {
            case 'BU':
                return 1;
            case 'BUR':
                return 1;
            case 'CH':
                return 2;
            case 'EM':
                return 3;
            case 'FR':
                return 4;
            case 'GI':
                return 5;
            case 'GO':
                return 6;
            case 'LA':
                return 7;
            case 'MO':
                return 8;
            case 'NA':
                return 9;
            case 'NAV':
                return 9;
            case 'PA':
                return 10;
            default:
                break;
        }

        /* Everything else, and houses "M" and "S"
         */
        return 0;
    }
}
