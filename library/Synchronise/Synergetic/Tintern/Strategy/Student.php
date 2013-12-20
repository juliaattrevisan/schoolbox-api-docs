<?php
/**
 * Synchronise_Synergetic_Tintern_Strategy_Student
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author    Steve Chan0 <steve.chan@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Synergetic_Tintern_Strategy_Student extends Synchronise_Base_Strategy_Student {
    protected $title = "Update Schoolbox Student Details Synchronisation";

    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Synergetic_Tintern_Verbose();
    }

    /**
     * Alt email (if not set) is based off of the student's username.
     *
     * @param  array  $currentUser
     * @return string
     */
    public function getAltEmailFromUser($currentUser) {
        return $this->getUsernameFromUser($currentUser).'@alaress.com.au';
    }

    /**
     * Gets campus according to the following map:
	 *
	 * At tintern campuses are determined by gender and year level,
	 * not by the StudentCampus value
	 *
     * @param  array $currentUser
     * @return int
     */
    public function getDefaultCampusFromUser($currentUser) {
		new Alaress_Probe($currentUser);

		$map = array();

		switch ($currentUser['StudentYearLevel']) {
			case 0:
				return 'P';
			case 30:
			case 32:
			case 33:
			case 40:
			case 50:
				return 'ELC';
			default:
				return $currentUser['StudentYearLevel'];
		}
    }

    /**
     * Gets year level for students.
     *
     * 30-50:    ELC
     * 0:         P (Prep)
     * Otherwise: whatever StudentYearLevel is
     *
     * @param  array  $currentUser
     * @return string
     */
    public function getYearFromUser($currentUser) {
        switch ($currentUser['StudentYearLevel']) {
			case 0:
				return 'P';
			case 30:
			case 32:
			case 33:
			case 40:
			case 50:
				return 'ELC';
            default:
                return $currentUser['StudentYearLevel'];
        }
    }

    /**
     * Groups for students:
     *
     * * 6  (ELC)
     * * 19 (Prep)
     * * Otherwise, Year+6
     *
     * @param  array $currentUser
     * @return int
     */
    public function getGroupIDFromUser($currentUser) {
        $year = $this->getYearFromUser($currentUser);

        switch ($year) {
            case 'ELC':
                return 4;
            case 'P':
                return 6;
            default:
                return intval($year)+6;
        }
    }
}
