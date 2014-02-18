<?php
/**
 * Hydrator for campus for Meriden staff users.
 *
 * Rules are exactly the same as for other users, but instead runs from the
 * 'StaffCampus' field.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Meriden_Hydrator_Campus_Staff extends Synchronise_Tass_Meriden_Hydrator_Campus {
    protected $field = 'StaffCampus';

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
     * Mapped on staff department
     *
     * *  JUN => 2 (Junior School)
     * *  LIN => 2 (Junior School)
     * *  * => 1 (Senior School)
     *
     * @param  array $externalUser
     * @return int
     */
    public function getDefaultCampusFromUser($externalUser) {

        switch($externalUser['StaffDepartment']) {
            case 'JUN':
                return 2;
            case 'LIN':
                return 2;
            default:
                return 1;

        }
    }

}