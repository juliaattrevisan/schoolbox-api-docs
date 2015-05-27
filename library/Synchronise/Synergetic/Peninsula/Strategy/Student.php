<?php
/**
 * Synchronise_Synergetic_Peninsula_Strategy_Student
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Synergetic_Peninsula_Strategy_Student extends Synchronise_Base_Strategy_Student {
    protected $title = "Update Schoolbox Student Details Synchronisation";

    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Synergetic_Peninsula_Verbose();
    }

    /**
     * Alt email (if not set) is based off of the student's username.
     *
     * @param  array  $currentUser
     * @return string
     */
    public function getAltEmailFromUser($currentUser) {
        return $this->getUsernameFromUser($currentUser).'@tps.vic.edu.au';
    }

    /**
     * Gets campus according to the following map:
     *
     * * E      => Early Childhood (4)
     * * J      => Junior (3)
     * * M      => Middle (2)
     * * S, ELC => Senior (1)
     *
     * @param  array $currentUser
     * @return int
     */
    public function getDefaultCampusFromUser($currentUser) {
        $campus_code = strtoupper($currentUser['StudentCampus']);
        switch ($campus_code) {
            case 'S':
            case 'ELC':
                return 1;
            case 'M':
                return 2;
            case 'J':
                return 3;
            case 'E':
                return 4;
            default:
                break;
        }
        return 0;
    }

    /**
     * Gets year level for students.
     *
     * * 30, 40:    ELC
     * * O:         P (Prep)
     * * Otherwise: whatever StudentYearLevel is
     *
     * @param  array  $currentUser
     * @return string
     */
    public function getYearFromUser($currentUser) {
        switch ($currentUser['StudentYearLevel']) {
            case 30:
            case 40:
                return 'ELC';
            case 0:
                return 'P';
            default:
                return $currentUser['StudentYearLevel'];
        }
    }

    /**
     * Groups for students:
     *
     * * 6  (ELC)
     * * 21 (Prep)
     * * Otherwise, Year+6
     *
     * @param  array $currentUser
     * @return int
     */
    public function getGroupIDFromUser($currentUser) {
        $year = $this->getYearFromUser($currentUser);
        switch ($year) {
            case 'ELC':
                return 21;
            case 'P':
                return 6;
            default:
                return intval($year)+6;
        }
    }
    
    /**
    * Webmail setting
    *
    * @param $currentUser
    * @return int|void
    */
    public function getWebmailFromUser($currentUser) {
            return 1;
    }

    /**
    * External mail setting
    *
    * @param $currentUser
    * @return int|void
    */
    public function getExternalMailFromUser($currentUser) {
            return 0;
    }

    /**
     * Set eportfolio to on for 5,6,7,8
     * Don't touch the rest
     *
     * @param  array $currentUser
     *
     * @return bool
     */
    public function getEportfolioEnabledFromUser($currentUser)
    {
        switch ($currentUser['StudentYearLevel']) {
            case 5:
            case 6:
            case 7:
            case 8:
                return 1;
            break;
            default:
                return null;
        }
    }

}
