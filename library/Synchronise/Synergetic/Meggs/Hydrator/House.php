<?php
/**
 * Hydrator for house for Meggs users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Meggs_Hydrator_House implements Synchronise_Base_IHydrator
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
     * * GR => 20 (Grammar)
     * * KT => 21 (Kent)
     * * MC => 22 (McCowan)
     * * SM => 23 (St. Margaret's)
     *
     * @param  array $externalUser
     * @return int
     */
    public function getHouseFromUser($externalUser)
    {
        switch (strtoupper(trim($externalUser['StudentHouse']))) {
            case 'GR':
                return 20;
            case 'KT':
                return 21;
            case 'MC':
                return 22;
            case 'SM':
                return 23;
            default:
                return 0;
        }
    }
}
