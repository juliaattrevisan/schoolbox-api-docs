<?php
/**
 * Synchronise_Synergetic_StKevins_Strategy_Student
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Synergetic_StKevins_Strategy_Student extends Synchronise_Base_Strategy_Student {
	protected $title = "Update Schoolbox Student Details Synchronisation";

	/**
	 * Setup verbose output
	 */
	protected function setup() {
		$this->verbose_filter = new Synchronise_Synergetic_StKevins_Verbose();
	}

	/**
	 * Student's username is always the student ID, unless the NetworkLogin
	 * field value is defined.
	 *
	 * @param  array  $currentUser
	 * @return string
	 */
	public function getUsernameFromUser($currentUser) {
		$username = parent::getUsernameFromUser($currentUser);
		if (!(strlen($username) > 0)) {
			$username = $this->getExternalIdFromUser($currentUser);
		}
		return $username;
	}

	/**
	 * Alt email (if not set) is based off of the student's username (which for
	 * St. Kevins is also their ID).
	 *
	 * @param  array  $currentUser
	 * @return string
	 */
	public function getAltEmailFromUser($currentUser) {
		$email = parent::getAltEmailFromUser($currentUser);
		if (!isset($email)) {
			$email = $this->getUsernameFromUser($currentUser).'@student.stkevins.vic.edu.au';
		}
		return $email;
	}

	/**
	 * Gets campus according to the following map:
	 *
	 * * J (Glendalough) => 1
	 * * W (Waterford)   => 2
	 * * S (Heyington)   => 3
	 *
	 * @param  array $currentUser
	 * @return int
	 */
	public function getDefaultCampusFromUser($currentUser) {
		$campus_code = strtoupper($currentUser['StudentCampus']);
		switch ($campus_code) {
			case 'J':
				return 1;
			case 'W':
				return 2;
			case 'S':
				return 3;
			default:
				break;
		}
		return 0;
	}

	/**
	 * Groups for St. Kevins students:
	 *
	 * * 7  (Years 3-6)
	 * * 8  (Year 7)
	 * * 9  (Year 8)
	 * * 10 (Year 9)
	 * * 11 (Year 10-12)
	 *
	 * @param  array $currentUser
	 * @return int
	 */
	public function getGroupIDFromUser($currentUser) {
		$year  = $this->getYearFromUser($currentUser);
		$group = 0;
		if (in_array($year, array(3, 4, 5, 6,))) {
			$group = 7;
		}
		elseif ($year == 7) {
			$group = 8;
		}
		elseif ($year == 8) {
			$group = 9;
		}
		elseif ($year == 9) {
			$group = 10;
		}
		elseif (in_array($year, array(10, 11, 12,))) {
			$group = 11;
		}
		return $group;
	}

	/**
	 * St. Kevins doesn't currently enable eportfolio for students.
	 *
	 * @param  array $currentUser
	 * @return bool
	 */
	public function getEportfolioEnabledFromUser($currentUser) {
		return false;
	}
}
