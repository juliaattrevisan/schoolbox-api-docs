<?php
/**
 * Synchronise_Schoolbox_Nhs_Strategy_Staff
 *
 * Update Schoolbox with Schoolbox data if there is something different
 * contained in Schoolbox database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_External_Nhs_Strategy_Staff extends Synchronise_Base_Strategy_Staff {


    // StaffID, NetworkLogin, ActiveFlag,
    // StaffTitle, StaffPreferredName, StaffSurname,
    // DefaultEmailCode, OccupEmail, Email,
    // StaffCampus, StaffCategoryType, StaffCategory
    // Try to map columns from export as closely to Synergetic returns.
    protected $columns = array(
        'StaffID',
        'NetworkLogin',
        'StaffSurname',
        'StaffGivenName',
        'StaffPreferredName',
        'StaffMiddleName',
        'StaffGender',
        null,
        null,
        null,
        'OccupEmail',
        'StaffPhone',
        'StaffTeacherNumber',
        null,
        'StaffBirthDate',
        'StaffSubAddress',
        'StaffAddress',
        'StaffSuburb',
        'StaffState',
        'StaffPostcode',
        'StaffCountry',
        'StaffHomePhone',
        null,
        null,
        null,
        null,
        null,
        'StaffFullName',
    );

    protected $title = "Update Schoolbox Staff Details Synchronisation";

    /**
     * Creates a list from a CSV file instead of from a DB connection
     */
    public function generateList() {
        $this->list = array();
        // TODO move this somewhere into its own function
        $fp = fopen(__DIR__.'/staff.csv', 'r');
        while (($row = fgetcsv($fp, null, ',', '"')) !== false) {
            $list_row = array();
            foreach ($this->columns as $i => $column) {
                if (isset($column)) {
                    $list_row[$column] = isset($row[$i]) ? $row[$i] : null;
                }
            }
            $this->list[] = $list_row;
        }
        fclose($fp);
    }

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
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_External_Nhs_Verbose();
    }

    /**
     * Returns 1 until further notice.
     *
     * @param  array $currentUser
     * @return int
     */
    public function getDefaultCampusFromUser($currentUser) {
        return 1;
    }

    // The Schoolbox connector doesn't return anything for getStaff().
}
