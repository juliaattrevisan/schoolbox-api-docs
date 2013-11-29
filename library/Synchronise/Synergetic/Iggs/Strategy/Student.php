<?php
/**
 * Synchronise_Synergetic_Iggs_Strategy_Student
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Synergetic_Iggs_Strategy_Student extends Synchronise_Base_Strategy_Student {
    protected $title = "Update Schoolbox Student Details Synchronisation";

    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Synergetic_Iggs_Verbose();
    }

    /**
     * Houses:
     * * Ashby:      20
     * * Locksley:   21
     * * Oswald:     22
     * * Rotherwood: 23
     * * Rowena:     24
     * * York:       25
     *
     * @param  array $currentUser
     * @return int
     */
    public function getHouseFromUser($currentUser) {
        switch ($currentUser['StudentHouse']) {
            case 'ASH':
                return 20;
            case 'LOC':
                return 21;
            case 'OSW':
                return 22;
            case 'ROT':
                return 23;
            case 'ROW':
                return 24;
            case 'YOR':
                return 25;
        }
        return null;
    }

    /**
     * Gets campus according to the following map:
     *
     * * S (Senior) => 1
     * * J (Junior) => 2
     * * E (ELC)    => 3
     *
     * @param  array $currentUser
     * @return int
     */
    public function getDefaultCampusFromUser($currentUser) {
        switch (strtoupper($currentUser['StudentCampus'])) {
            case 'S':
                return 1;
            case 'J':
                return 2;
            case 'E':
                return 3;
        }
        return 0;
    }

    /**
     * Groups for students:
     *
     * * 1-12: Year+5
     * * Prep: 18
     * * 4YO:  19
     * * 3YO:  20
     *
     * @param  array $currentUser
     * @return int
     */
    public function getGroupIDFromUser($currentUser) {
        $year  = strtoupper($this->getYearFromUser($currentUser));
        $group = 0;
        if (in_array($year, range(1, 12))) {
            $group = intval($year) + 5;
        }
        elseif ($year == 'PREP') {
            $group = 18;
        }
        elseif ($year == '4YO') {
            $group = 19;
        }
        elseif ($year == '3YO') {
            $group = 20;
        }
        return $group;
    }
}
