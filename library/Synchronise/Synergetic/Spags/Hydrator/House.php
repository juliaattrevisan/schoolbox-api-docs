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
     *
     * * BE => 20 (Bedggood)
     * * BO => 21 (Boyne)
     * * CL => 22 (Clarke)
     * * HU => 23 (Hughston)
     * * MA => 24 (Maxwell)
     * * ME => 25 (Menzies)
     * * MU => 26 (Murdoch)
     * * OW => 27 (Ower)
     * * RE => 28 (Reid)
     *
     * @param  array $externalUser
     * @return int
     */
    public function getHouseFromUser($externalUser) {
        switch (strtoupper(trim($externalUser['StudentHouse']))) {
            case 'BE':
                return 20;
            case 'BO':
                return 21;
            case 'CL':
                return 22;
            case 'HU':
                return 23;
            case 'MA':
                return 24;
            case 'ME':
                return 25;
            case 'MU':
                return 26;
            case 'OW':
                return 27;
            case 'RE':
                return 28;
            default:
                break;
        }
        return 0;
    }
}
