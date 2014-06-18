<?php
/**
 * Hydrator for campus for Cathedral users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Cathedral_Hydrator_Campus_Staff implements Synchronise_Base_IHydrator {

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $campus = $this->getDefaultCampusFromUser($externalUser);
        if ($campus) {
            $newUser['campus']        = array($campus);
            $newUser['defaultCampus'] = $campus;
        }
        return $newUser;
    }

    /**
     * Gets campus according to the following map:
     *
     * * Year 0-6   => 5 (Junior Campus)
     * * Year 7-9   => 2 (Middle Campus)
     * * Year 10-12 => 1 (Senior Campus)
     *
     * @param  array $externalUser
     * @return int
     * @throws RangeException if student's year isn't in the list above
     */
    public function getDefaultCampusFromUser($externalUser) {
        $year = $externalUser['StudentYearLevel'];
        if (in_array($year, array(0,1,2,3,4,5,6))) {
            return 5;
        }
        if (in_array($year, array(7,8,9))) {
            return 2;
        }
        if (in_array($year, array(10,11,12))) {
            return 1;
        }
        throw new RangeException('Student\'s year is not in range 0-12');
    }
}
