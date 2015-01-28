<?php
/**
 * Hydrator for house for Toowoomba students.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Toowoomba_Hydrator_House_Student implements Synchronise_Base_IHydrator
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
     * Houses for Toowoomba students
     *
     * @param  array $externalUser
     * @return int
     * @throws RangeException if house not recognized
     */
    public function getHouseFromUser($externalUser)
    {
        switch ($externalUser['StudentHouse']) {
            case 'GI':
                // Gill
                return 20;
            case 'FA':
                // Fairfax
                return 21;
            case 'CO':
                // Connal
                return 22;
            default:
                throw new RangeException('Student has no recognized house');
        }
    }
}
