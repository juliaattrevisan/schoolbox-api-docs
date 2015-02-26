<?php
/**
 * Hydrator for role for Meggs students.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Meggs_Hydrator_Student_Role implements Synchronise_Base_IHydrator
{
    const ROLE_JUNIOR_STUDENTS = 5;
    const ROLE_SENIOR_STUDENTS = 3;

    /**
     * Hydrates role based on year level.
     *
     * * Junior school: 5
     * * Senior school: 6
     */
    public function hydrate($externalUser, $newUser)
    {
        switch ($externalUser['StudentYearLevel']) {
            case 'ELC':
            case 'PREP':
            case 20:
            case 30:
            case 40:
            case 0:
            case 1:
            case 2:
            case 3:
            case 4:
            case 5:
            case 6:
                $role = self::ROLE_JUNIOR_STUDENTS;
                break;
            case 7:
            case 8:
            case 9:
            case 10:
            case 11:
            case 12:
                $role = self::ROLE_SENIOR_STUDENTS;
                break;
            default:
                throw new Exception(
                    sprintf(
                        'Year level "%s" not recognized',
                        $externalUser['StudentYearLevel']
                    )
                );
        }
        $newUser['group_id'] = $role;
        return $newUser;
    }
}
