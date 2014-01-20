<?php
/**
 * Hydrator for group for Meriden students.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Meriden_Hydrator_Student_Group implements Synchronise_Base_Strategy_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['group_id'] = $this->getGroupIDFromUser($externalUser);
        return $newUser;
    }

    /**
     * Groups for Meriden students are based on year level:
     *
     * * 0,-1 => 6    (Pre-school)
     * * 1-6  => 3    (Junior School)
     * * 7-12 => 7-12 (same as year level)
     *
     * @param  array $externalUser
     * @return int
     */
    public function getGroupIDFromUser($externalUser) {
        $year  = $externalUser['StudentYearLevel'];
        $group = 0;
        if (in_array($year, array(0, -1,))) {
            $group = 6;
        }
        if (in_array($year, array(1, 2, 3, 4, 5, 6,))) {
            $group = 3;
        }
        elseif (in_array($year, array(7, 8, 9, 10, 11, 12,))) {
            $group = $year;
        }
        return $group;
    }
}