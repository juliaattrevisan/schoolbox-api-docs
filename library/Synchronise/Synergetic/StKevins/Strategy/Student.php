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
	 * Student's username is always the student ID.
	 *
	 * @param  array  $currentUser
	 * @return string
	 */
	public function getUsernameFromUser($currentUser) {
		return $this->getExternalIdFromUser($currentUser);
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
	 * Campuses for St. Kevins students:
	 *
	 * * 1 (Glendalough): years 3-6
	 * * 2 (Waterford):   year 9
	 * * 3 (Heyington):   years 7-8, 10-12
	 *
	 * @param array $currentUser
	 *
	 * @return int
	 */
	public function getDefaultCampusFromUser($currentUser) {
		$year   = $this->getYearFromUser($currentUser);
		$campus = 0;
		if (in_array($year, array(3, 4, 5, 6,))) {
			$campus = 1;
		}
		elseif ($year == 9) {
			$campus = 2;
		}
		elseif (in_array($year, array(7, 8, 10, 11, 12,))) {
			$campus = 3;
		}
		return $campus;
	}
}
