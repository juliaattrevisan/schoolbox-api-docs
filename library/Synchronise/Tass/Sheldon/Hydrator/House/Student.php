<?php
/**
 * Hydrator for house for Sheldon students.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Sheldon_Hydrator_House_Student implements Synchronise_Base_IHydrator
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
     * @param  array $externalUser
     * @return int
     * @throws RangeException if house not recognized
     */
    public function getHouseFromUser($externalUser)
    {
        switch ($externalUser['StudentHouse']) {
            case 'IN':
                // Integra
                return 1;
            case 'QU':
                // Qualitas
                return 2;
            case 'SP':
                // Spiro
                return 3;
            case 'VA':
                // Valeo
                return 4;
            default:
                throw new RangeException('Student has no recognized house');
        }
    }
}
