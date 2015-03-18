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

    protected $location = '';
    protected $columns  = array();
    protected $title    = "Update Schoolbox Student Details Synchronisation";

    /**
     * Sets up synchronisation with parameters passed from the definition.
     *
     * @param string $location Where the CSV for import is located
     * @param array  $columns  The columns to import
     */
    public function __construct($location, $columns) {
        parent::__construct();
        $this->location = $location;
        $this->columns  = $columns;
    }

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
        $reader = new Alaress\Csv\Reader($this->location);
        $reader->setColumnList($this->columns);
        foreach ($reader as $row) {
            $list_row['StudentOccupEmail'] = $row['StudentEmail'];
            $this->list[] = $row;
        }
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
     * Groups for Northcote High students: Student Year - 2, except year 7
     * (group id = 4)
     *
     * @param  array $currentUser
     * @return int
     */
    public function getGroupIDFromUser($currentUser) {
        $year = $this->getYearFromUser($currentUser);
        switch ($year) {
            case 7:
                return 4;
            case 8:
                return 6;
            case 9:
                return 7;
            case 10:
                return 8;
            case 11:
                return 9;
            case 12:
                return 10;
        }
        return 0;
    }

    /**
     * The "email address from username" field is enabled for NHS students.
     *
     * @param  array $currentUser
     * @return string
     */
    public function getLocalEmailFromUser($currentUser) {
        return 1;
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
            ->setDob(new Alaress_Date_Format($externalUser['StudentBirthDate'], 'Y-m-d'))
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
