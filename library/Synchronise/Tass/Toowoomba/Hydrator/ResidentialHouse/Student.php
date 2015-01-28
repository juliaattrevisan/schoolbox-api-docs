<?php
/**
 * Hydrator for residential house for Toowoomba students.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Toowoomba_Hydrator_ResidentialHouse_Student implements Synchronise_Base_IHydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser['residential_house_id'] = $this->getResidentialHouseFromUser($externalUser);
        return $newUser;
    }

    /**
     * Residential Houses for Toowoomba students
     *
     * * BRH: Brewster (1)
     * * AH: Arnott (2)
     * * BH: Bligh (3)
     *
     * @param  array $externalUser
     * @return int
     * @throws RangeException if house not recognized
     */
    public function getResidentialHouseFromUser($externalUser)
    {
        switch ($externalUser['StudentBoardingHouse']) {
            case 'BRH':
                return 1;
            case 'AH':
                return 2;
            case 'BH':
                return 3;
            default:
                return 0;
        }
    }
}
