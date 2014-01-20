<?php
/**
 * Hydrator for house for Meriden users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Meriden_Hydrator_House implements Synchronise_Base_IHydrator {
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
     * * S => 20 (Sussex)
     * * C => 21 (Cumberland)
     * * K => 22 (Kent)
     * * W => 23 (Warwick)
     *
     * @param  array $externalUser
     * @return int
     */
    public function getHouseFromUser($externalUser) {
        switch (strtoupper(trim($externalUser['StudentHouse']))) {
            case 'S':
                return 20;
            case 'C':
                return 21;
            case 'K':
                return 22;
            case 'W':
                return 23;
            default:
                break;
        }
        return 0;
    }
}
