<?php
/**
 * Hydrator for residential house for St Catherine's users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Scs_Hydrator_ResidentialHouse implements Synchronise_Base_IHydrator
{
    /**
     * There is only one boarding house:
     * 1: Illawarra
     *
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser['residential_house_id'] = 0;
        if ($externalUser['StudentBoarder'] === 'B') {
            switch ($externalUser['StudentBoardingHouse']) {
                case 'ILL':
                    $newUser['residential_house_id'] = 1;
            }
        }
        return $newUser;
    }
}
