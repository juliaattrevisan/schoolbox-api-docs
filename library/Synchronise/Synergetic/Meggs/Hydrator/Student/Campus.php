<?php
/**
 * Hydrator for campus for Meggs students.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Meggs_Hydrator_Student_Campus implements Synchronise_Base_IHydrator
{
    /**
     * Hydrates user's campus and default campus based on year level.
     *
     * * ELC - ELC (1)
     * * Prep-6 - Junior (2)
     * * 7-12 - Senior (3)
     */
    public function hydrate($externalUser, $newUser)
    {
        switch (trim($externalUser['StudentYearLevel'])) {
            case 'ELC':
                $campus = 1;
                break;
            case 'PREP':
            case 1:
            case 2:
            case 3:
            case 4:
            case 5:
            case 6:
                $campus = 2;
                break;
            case 7:
            case 8:
            case 9:
            case 10:
            case 11:
            case 12:
                $campus = 3;
                break;
            default:
                throw new Exception("No campus found");
        }

        $newUser['campus'] = array($campus);
        $newUser['defaultCampus'] = $campus;
        return $newUser;
    }
}
