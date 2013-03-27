<?php
/**
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
class Synchronise_Edumate_Tigs_Strategy_Student extends Synchronise_Edumate_Tigs_Strategy_Abstract {

	/**
	 * Get a list of records to process.
	 *
	 * Copy-pasted, should be in base class.
	 *
	 * @return void
	 */
	public function generateList() {
		$this->collateList($this->getExternalUserHandler()->getStudentUserAccountInfo());
	}

	/**
	 * Set up the new user's details
	 *
	 * This should be the only method that needs to be overridden.
	 *
	 * @param  $currentUser
	 * @return array
	 */
	public function setupUserDetails($currentUser) {
		$newUser = array();

		// login details
		$newUser['username'] = $currentUser['NetworkLogin'];

		// user attributes
		$newUser['title']         = '';
		$newUser['firstname']     = $currentUser['StudentPreferred'];
		$newUser['lastname']      = $currentUser['StudentSurname'];
		$newUser['alt_email']     = $currentUser['StudentEmail'];
		$newUser['synergy_id']    = $currentUser['StudentID'];
		$newUser['local_email']   = 1;
		$newUser['year']          = $this->getYearCodeFromYearLevel($currentUser['StudentYearLevel']);
		//
		// No houses
//		$newUser['house']         = $this->getHouseFromCode($currentUser['StudentHouse']);
		$campus                   = $this->getCampusFromYearLevel($currentUser['StudentYearLevel']);
		$newUser['defaultCampus'] = $campus;
		$newUser['campus']        = array($campus);
		$newUser['group_id']      = $this->getGroupIDFromYearLevel($currentUser['StudentYearLevel']);

		$newUser['eportfolio_enabled'] = false;

		// students aren't superusers
		//$newUser['su'] = false;

		return $newUser;
	}

	protected function getYearCodeFromYearLevel($year_level) {
		$codes = array(
			"Kindergarten" => 'K',
			"Pre-Prep"     => 'PP',
			"Preparatory"  => 'P',
			"Year 01"      => 1,
			"Year 02"      => 2,
			"Year 03"      => 3,
			"Year 04"      => 4,
			"Year 05"      => 5,
			"Year 06"      => 6,
			"Year 07"      => 7,
			"Year 08"      => 8,
			"Year 09"      => 9,
			"Year 10"      => 10,
			"Year 11"      => 11,
			"Year 12"      => 12,
		);
		return isset($codes[$year_level]) ? $codes[$year_level] : 0;
	}

	protected function getGroupIDFromYearLevel($year_level) {
		$groups = array(
			"Kindergarten" => 4,
			"Pre-Prep"     => 2,
			"Preparatory"  => 3,
			"Year 01"      => 6,
			"Year 02"      => 7,
			"Year 03"      => 8,
			"Year 04"      => 9,
			"Year 05"      => 10,
			"Year 06"      => 11,
			"Year 07"      => 12,
			"Year 08"      => 13,
			"Year 09"      => 14,
			"Year 10"      => 15,
			"Year 11"      => 16,
			"Year 12"      => 17,
		);
		return isset($groups[$year_level]) ? $groups[$year_level] : 0;
	}

	protected function getCampusFromYearLevel($year_level) {
		$campuses = array(
			"Kindergarten" => 2,
			"Pre-Prep"     => 2,
			"Preparatory"  => 2,
			"Year 01"      => 2,
			"Year 02"      => 2,
			"Year 03"      => 2,
			"Year 04"      => 2,
			"Year 05"      => 2,
			"Year 06"      => 2,
			"Year 07"      => 1,
			"Year 08"      => 1,
			"Year 09"      => 1,
			"Year 10"      => 1,
			"Year 11"      => 1,
			"Year 12"      => 1,
		);
		return isset($campuses[$year_level]) ? $campuses[$year_level] : 0;
	}
}
