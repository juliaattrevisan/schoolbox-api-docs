<?php
/**
 * Hydrator for house for Loyola users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Loyola_Hydrator_House implements Synchronise_Base_IHydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser['house'] = $this->getHouseFromUser($externalUser);
        return $newUser;
    }

    /**
     * Gets house according to the following map:
     *
     * A: 1 (MCAULEY)
     * C: 2 (CHISHOLM)
     * F: 3 (FLYNN)
     * K: 4 (MACKILLOP)
     * M: 5 (MANNIX)
     * X: 6 (XAVIER)
     *
     * @param  array $externalUser
     * @return int
     */
    public function getHouseFromUser($externalUser)
    {
        switch (strtoupper(trim($externalUser['StudentHouse']))) {
            case 'A':
                return 1;
            case 'C':
                return 2;
            case 'F':
                return 3;
            case 'K':
                return 4;
            case 'M':
                return 5;
            case 'X':
                return 6;
            default:
                return 0;
        }
    }
}
