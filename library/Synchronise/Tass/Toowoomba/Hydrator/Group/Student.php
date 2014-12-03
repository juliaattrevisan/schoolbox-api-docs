<?php
/**
 * Hydrator for group for Toowoomba students.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Toowoomba_Hydrator_Group_Student implements Synchronise_Base_IHydrator
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
                // ELC
                return 3;
            case 0:
                // Prep
                return 20;
            case 1:
                return 6;
            case 2:
                return 7;
            case 3:
                return 8;
            case 4:
                return 9;
            case 5:
                return 10;
            case 6:
                return 11;
            case 7:
                return 12;
            case 8:
                return 13;
            case 9:
                return 14;
            case 10:
                return 15;
            case 11:
                return 16;
            case 12:
                return 17;
            default:
                throw new RangeException('Student has no recognized group');
        }
    }
}
