<?php
/**
 * Synchronise_External_Winifred_Student
 *
 * Strategy to update/import student records from Winifred' Denbigh database to Schoolbox
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @stolen_from Matt Day <matt.day@alaress.com.au>
 */
class Synchronise_Denbigh_Winifred_Strategy_Student extends Synchronise_Denbigh_Winifred_Strategy_Abstract {

    /**
     * Generate a list of student Denbigh IDs
     */
    public function generateList() {
        $this->collateList($this->getExternalUserHandler()->getStudentUserAccountInfo());
    }

    /**
     * Creates the users and inserts them into the database (unless they already exist)
     */
    public function create() {
        $this->getVerboseFilter()->intro();

        foreach($this->getList() as $currentUser) {
            $this->executeCreateUpdate($this->generateUserAttributes($currentUser));
        }

        $this->getVerboseFilter()->summary();
    }

    /**
     * Map the attributes of the current user to Schoolbox fields
     * @param $currentUser
     * @return array
     */
    public function generateUserAttributes($currentUser) {
        $newUser = array();

        // set attributes
        $newUser['username']             = $currentUser['username'];
//        $newUser['password']             = 'abc123';
        $newUser['group_id']             = $this->getGroupIDFromYearLevel($currentUser['academicYear']);
        $newUser['title']                = '';
        $newUser['firstname']            = $currentUser['firstname'];
        $newUser['lastname']             = $currentUser['surname'];
//        $newuser['email_pass']           = 'abc123';
        $newUser['synergy_id']           = $currentUser['denbighID'];
        $newUser['campus']               = array($this->getCampusFromYearLevel($currentUser['academicYear']));
        $newUser['year']                 = $currentUser['academicYear'];
        $newUser['house']                = $this->getHouse($currentUser['house']);
        $newUser['residential_house_id'] = $this->getResidentialHouse($currentUser['cateringHouse']);
        $newUser['alt_email']            = $this->convertEmail($currentUser['email']);
        $newUser['local_email']          = 1;
        $newUser['su']                   = false;
        $newUser['defaultCampus']        = $this->getCampusFromYearLevel($currentUser['academicYear']);
        $newUser['eportfolio_enabled']   = self::EPORTFOLIO_STUDENT_ENABLED ? 1 : 0;
//        if (Cfg::get('boarder_leave_enabled')) {
//            $newUser['boarding_status']  = $currentUser['boardingStatus'];
//        }

        return $newUser;
    }

    /**
     * Strips all non-alphanumeric characters out of email address.
     *
     * @param  string $email
     * @return string
     */
    protected function convertEmail($email) {
        return preg_replace('/[^a-z0-9\\.\\@]/i', '', iconv('UTF-8', 'ASCII//TRANSLIT', $email));
    }
}
