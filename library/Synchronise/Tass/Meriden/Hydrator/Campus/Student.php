<?php
/**
 * Hydrator for campus for Meriden users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Meriden_Hydrator_Campus_Student extends Synchronise_Tass_Meriden_Hydrator_Campus {

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $campus = $this->getDefaultCampusFromUser($externalUser);
        if ($campus) {
            $newUser['campus']        = array($campus);
            $newUser['defaultCampus'] = $campus;
        }

        if ($externalUser['StudentYearLevel'] < 7 || $externalUser['StudentYearLevel'] > 12) {
            throw new Exception('Not active user');
        }

        return $newUser;
    }


    /**
     * Gets campus according to the following map:
     *
     * *  Year 7-12 => 1 (Senior School)
     * *  Year 0-6 => 2 (Junior School)
     *
     * @param  array $externalUser
     * @return int
     */
    public function getDefaultCampusFromUser($externalUser) {

        if ($externalUser['StudentYearLevel'] < 7) {
            return 2;
        }
        else {
            return 1;
        }
    }
}
