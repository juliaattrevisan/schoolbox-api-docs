<?php
/**
 * Hydrator for groups for Loyola students.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Loyola_Hydrator_Group_Student implements Synchronise_Base_IHydrator
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
     * Groups are based on year level:
     *
     * * 7  => 3
     * * 8  => 5
     * * 9  => 6
     * * 10 => 7
     * * 11 => 8
     * * 12 => 9
     *
     * @param  array $externalUser
     * @return int
     * @throws Exception if year level not in list
     */
    public function getGroupIDFromUser($externalUser)
    {
        $year = $externalUser['StudentYearLevel'];
        switch ($year) {
            case 7:
                return 3;
            case 8:
                return 5;
            case 9:
                return 6;
            case 10:
                return 7;
            case 11:
                return 8;
            case 12:
                return 9;
            default:
                throw new Exception('student\'s year level not from 7-12');
        }
    }
}
