<?php
/**
 * Synchronise_Schoolbox_Nhs_Strategy_Student
 *
 * Update Schoolbox with Schoolbox data if there is something different
 * contained in Schoolbox database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\External\UserProfile;
use Schoolbox\External\UserProfileMapper;

class Synchronise_External_Nhs_Strategy_Student extends Synchronise_Base_Strategy_Student {

    // Try to map columns from export as closely to Synergetic returns.
    protected $columns = array(
        'StudentID',
        'NetworkLogin',
        'StudentSurname',
        'StudentGiven',
        'StudentPreferred',
        'StudentMiddleName',
        'StudentGender',
        'StudentClassGroup',
        'StudentHouse',
        'StudentYearLevel',
        'StudentEmail',
        'StudentPhone',
        'StudentVceNumber',
        null,
        'StudentBirthDate',
    );

    protected $title = "Update Schoolbox Student Details Synchronisation";

    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_External_Nhs_Verbose();
    }

    /**
     * Creates a list from a CSV file instead of from a database connection.
     */
    public function generateList() {
        $this->list = array();
        // Where's the CSV file? Let's say in students_csv for now.
        // Need a better method for loading students too.
        $fp = fopen(__DIR__.'/students.csv', 'r');
        while (($row = fgetcsv($fp, null, ',', '"')) !== false) {
            $list_row = array();
            foreach ($this->columns as $i => $column) {
                if (isset($column)) {
                    $list_row[$column] = isset($row[$i]) ? $row[$i] : null;
                }
            }
            $list_row['StudentOccupEmail'] = $list_row['StudentEmail'];
            $this->list[] = $list_row;
        }
        fclose($fp);
    }

    // Usually overridden:
    // getDefaultCampusFromUser
    // getGroupIDFromUser

    /**
     * Until further notice, there are no titles.
     *
     * @param  array $currentUser
     * @return string
     */
    public function getTitleFromUser($currentUser) {
        return '';
    }

    /**
     * Until further notice, this always returns 1 (Main).
     *
     * @param  array $currentUser
     * @return int
     */
    public function getDefaultCampusFromUser($currentUser) {
        return 1;
    }

    /**
     * Groups for Northcote High students: Student Year - 3
     *
     * @param  array $currentUser
     * @return int
     */
    public function getGroupIDFromUser($currentUser) {
        $year = $this->getYearFromUser($currentUser);
        return ($year >= 7 && $year <= 12) ? ($year - 3) : 0;
    }

    /**
     * Sticks stuff into the Schoolbox connector.
     *
     * @param array $newUser
     * @param array $externalUser
     */
    protected function executePostCreateUpdate($newUser, $externalUser) {
        try {
            $profile = UserProfileMapper::getInstance()->findById($newUser['id']);
            if (!$profile) {
                throw new Exception('User Profile does not exist');
            }
        }
        catch (Exception $e) {
            $profile = new UserProfile();
            $profile->setUserId($newUser['id']);
        }
        $profile
            ->setHouse($externalUser['StudentHouse'])
            ->setLevel($externalUser['StudentYearLevel'])
            ->setDob(new Alaress_Date_Format(new DateTime($externalUser['StudentBirthDate'])))
            ->setBirthDate($externalUser['StudentBirthDate'])
            ->setHomegroup($externalUser['StudentClassGroup'])
            ->setGivenName($externalUser['StudentPreferred'])
            ->setLastName($externalUser['StudentSurname'])
            ->setGender($externalUser['StudentGender'])
            ->setEmail($externalUser['StudentEmail'])
        ;
        UserProfileMapper::getInstance()->save($profile);
    }

}
