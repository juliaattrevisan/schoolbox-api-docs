<?php
/**
 * Synchronise_Tass_Meriden_Strategy_Student
 *
 * Update Schoolbox with Tass data if there is something different
 * contained in Tass database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Tass_Meriden_Strategy_Student extends Synchronise_Base_Strategy_Student {
    protected $title = "Update Schoolbox Student Details Synchronisation";

    /**
     * Get a list of students to sync
     *
     * @return void
     */
    public function generateList() {
        $this->list = $this->external_user_handler->getStudentUserAccountInfo();
    }

    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Tass_Meriden_Verbose();
    }

    /**
     * Gets campus according to the following map:
     *
     * * SS => 1 (Senior School)
     * * JS => 2 (Junior School)
     *
     * @param  array $currentUser
     * @return int
     */
    public function getDefaultCampusFromUser($currentUser) {
        switch (strtoupper(trim($currentUser['StudentCampus']))) {
            case 'JS':
                return 1;
            case 'SS':
                return 2;
            default:
                break;
        }
        return 0;
    }

    /**
     * Gets house according to the following map:
     *
     * * S => 20 (Sussex)
     * * C => 21 (Cumberland)
     * * K => 22 (Kent)
     * * W => 23 (Warwick)
     *
     * @param  array $currentUser
     * @return int
     */
    public function getHouseFromUser($currentUser) {
        switch (strtoupper(trim($currentUser['StudentHouse']))) {
            case 'S':
                return 20;
            case 'C':
                return 21;
            case 'K':
                return 22;
            case 'W':
                return 23;
            default:
                break;
        }
        return 0;
    }

    /**
     * Groups for Meriden students are based on year level:
     *
     * * 0,-1 => 6    (Pre-school)
     * * 1-6  => 3    (Junior School)
     * * 7-12 => 7-12 (same as year level)
     *
     * @param  array $currentUser
     * @return int
     */
    public function getGroupIDFromUser($currentUser) {
        $year  = $this->getYearFromUser($currentUser);
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
