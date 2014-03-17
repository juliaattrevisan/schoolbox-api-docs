<?php
/**
 * Hydrator for group for Cathedral students.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Cathedral_Hydrator_Group_Student implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['group_id'] = $this->getGroupIDFromUser($externalUser);
        return $newUser;
    }

    /**
     * Groups for Cathedral students are based on year level:
     *
     * * 0-6   => 6   (Jnr Students)
     * * 7-9   => 7-9 (same as year level)
     * * 10-12 => 10  (Snr Students)
     *
     * @param  array $externalUser
     * @return int
     */
    public function getGroupIDFromUser($externalUser) {
        $year  = $externalUser['StudentYearLevel'];
        $group = 0;
        if (in_array($year, array(0, 1, 2, 3, 4, 5, 6,))) {
            $group = 6;
        }
        if (in_array($year, array(7, 8, 9,))) {
            $group = $year;
        }
        elseif (in_array($year, array(10, 11, 12,))) {
            $group = 10;
        }
        return $group;
    }
}
