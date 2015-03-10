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
    const CAMPUS_ELC  = 1;
    const CAMPUS_JNR  = 2;
    const CAMPUS_YR7  = 3;
    const CAMPUS_YR8  = 4;
    const CAMPUS_YR9  = 5;
    const CAMPUS_YR10 = 6;
    const CAMPUS_YR11 = 7;
    const CAMPUS_YR12 = 8;

    /**
     * Hydrates user's campus and default campus based on year level.
     *
     * * ELC - ELC (1)
     * * Prep-6 - Junior (2)
     * * 7-12 - per year (3-8)
     */
    public function hydrate($externalUser, $newUser)
    {
        switch (trim($externalUser['StudentYearLevel'])) {
            case 'ELC':
            case 20:
            case 30:
            case 40:
                $campus = self::CAMPUS_ELC;
                break;
            case 'PREP':
            case 0:
            case 1:
            case 2:
            case 3:
            case 4:
            case 5:
            case 6:
                $campus = self::CAMPUS_JNR;
                break;
            case 7:
                $campus = self::CAMPUS_YR7;
                break;
            case 8:
                $campus = self::CAMPUS_YR8;
                break;
            case 9:
                $campus = self::CAMPUS_YR9;
                break;
            case 10:
                $campus = self::CAMPUS_YR10;
                break;
            case 11:
                $campus = self::CAMPUS_YR11;
                break;
            case 12:
                $campus = self::CAMPUS_YR12;
                break;
            default:
                throw new Exception("Could not map campus from year '{$externalUser['StudentYearLevel']}'");
        }

        $newUser['campus'] = array($campus);
        $newUser['defaultCampus'] = $campus;
        return $newUser;
    }
}
