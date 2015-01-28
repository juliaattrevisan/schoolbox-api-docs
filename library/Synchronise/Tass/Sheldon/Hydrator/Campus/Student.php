<?php
/**
 * Hydrator for campus for Sheldon students.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Sheldon_Hydrator_Campus_Student implements Synchronise_Base_IHydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $campus = $this->getDefaultCampusFromUser($externalUser);
        if ($campus) {
            $newUser['campus']        = array($campus);
            $newUser['defaultCampus'] = $campus;
        }
        return $newUser;
    }

    /**
     * Campus depends on year level
     *
     * @param  array $externalUser
     * @return int
     */
    public function getDefaultCampusFromUser($externalUser)
    {
        switch ($externalUser['StudentYearLevel']) {
            // ELC
            case -3:
            case -1:
            // Prep
            case 0:
            case 1:
            case 2:
            case 3:
            case 4:
                // Junior College
                return 2;
            case 5:
            case 6:
            case 7:
            case 8:
                // Middle College
                return 3;
            case 9:
            case 10:
            case 11:
            case 12:
                // Senior College
                return 1;
            default:
                throw new RangeException('Student has no recognized campus');
        }
    }
}
