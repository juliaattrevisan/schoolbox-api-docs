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
     * @param  array $externalUser
     * @return int
     * @throws RangeException if house not recognized
     */
    public function getResidentialHouseFromUser($externalUser)
    {
        switch ($externalUser['boarder']) {
            case 'Y':
                // I don't know how to tell which board house a student is in
                return 1;
            case 'N':
            default:
                return 0;
        }
    }
}
