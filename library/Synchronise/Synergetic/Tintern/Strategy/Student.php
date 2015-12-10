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
        return $this->getUsernameFromUser($currentUser).'@tintern.vic.edu.au';
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
     * Gets campus according to the following map:
	 *
	 * At tintern campuses are determined by gender and year level,
	 * not by the StudentCampus value
	 *
     * @param  array $currentUser
     * @return int
     */
    public function getDefaultCampusFromUser($currentUser) {
		$map = array();

		/* ELC for everyone
		 */
		$map[30]['F'] = 6;
		$map[32]['F'] = 6;
		$map[33]['F'] = 6;
		$map[40]['F'] = 6;
		$map[42]['F'] = 6;
		$map[43]['F'] = 6;
		$map[30]['M'] = 6;
		$map[32]['M'] = 6;
		$map[33]['M'] = 6;
		$map[40]['M'] = 6;
		$map[42]['M'] = 6;
		$map[43]['M'] = 6;

		/* Junior School Girls
		 */
		$map[0]['F'] = 5;
		$map[1]['F'] = 5;
		$map[2]['F'] = 5;
		$map[3]['F'] = 5;
		$map[4]['F'] = 5;
		$map[5]['F'] = 5;
		$map[6]['F'] = 5;

		/* Junior School Boys
		 */
		$map[0]['M'] = 4;
		$map[1]['M'] = 4;
		$map[2]['M'] = 4;
		$map[3]['M'] = 4;
		$map[4]['M'] = 4;
		$map[5]['M'] = 4;
		$map[6]['M'] = 4;

		/* Middle School Girls aka Secondary School Girls
		 */
		$map[7]['F'] = 3;
		$map[8]['F'] = 3;
		$map[9]['F'] = 3;

		/* Middle School Boys aka Secondary School Boys
		 */
		$map[7]['M'] = 2;
		$map[8]['M'] = 2;
		$map[9]['M'] = 2;

		/* Senior School for everyone
		 */
		$map[10]['F'] = 1;
		$map[11]['F'] = 1;
		$map[12]['F'] = 1;
		$map[10]['M'] = 1;
		$map[11]['M'] = 1;
		$map[12]['M'] = 1;

		$yearLevel = $currentUser['StudentYearLevel'];
		$gender = $currentUser['StudentGender'];

		return $map[$yearLevel][$gender];
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
			case 42:
			case 43:
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

    protected function userExists($user) {
        return $this->user_handler->getBySynergy($user['synergy_id'], $user['group_id']);
    }
}
