<?php
/**
 * Hydrator for house for Genazzano users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Genazzano_Hydrator_House implements Synchronise_Base_IHydrator {
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
     * * CORRY => 20 (Corry)
     * * D HOU => 21 (D Houet)
     * * DOUGL => 22 (Douglas)
     * * GERDA => 23 (Gerda)
     * * STOCK => 24 (Stock)
     * * WINTE => 25 (Winter)
     *
     * @param  array $externalUser
     * @return int
     */
    public function getHouseFromUser($externalUser) {
        switch (strtoupper(trim($externalUser['StudentHouse']))) {
            case 'CORRY':
                return 20;
            case 'D HOU':
                return 21;
            case 'DOUGL':
                return 22;
            case 'GERDA':
                return 23;
            case 'STOCK':
                return 24;
            case 'WINTE':
                return 25;
            default:
                return 0;
        }
    }
}
