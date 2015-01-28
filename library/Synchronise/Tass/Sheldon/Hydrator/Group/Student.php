<?php
/**
 * Hydrator for group for Sheldon students.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Sheldon_Hydrator_Group_Student implements Synchronise_Base_IHydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser['group_id'] = $this->getGroupIDFromUser($externalUser);
        return $newUser;
    }

    /**
     * Groups for Toowoomba students
     *
     * @param  array $externalUser
     * @return int
     * @throws RangeException if group not recognized
     */
    public function getGroupIDFromUser($externalUser)
    {
        switch ($externalUser['StudentYearLevel']) {
            case -3:
            case -1:
                // Kinder?
                return 16;
            case 0:
                // Prep
                return 17;
            case 1:
                return 3;
            case 2:
                return 4;
            case 3:
                return 5;
            case 4:
                return 6;
            case 5:
                return 7;
            case 6:
                return 9;
            case 7:
                return 10;
            case 8:
                return 11;
            case 9:
                return 12;
            case 10:
                return 13;
            case 11:
                return 14;
            case 12:
                return 15;
            default:
                throw new RangeException('Student has no recognized group');
        }
    }
}
