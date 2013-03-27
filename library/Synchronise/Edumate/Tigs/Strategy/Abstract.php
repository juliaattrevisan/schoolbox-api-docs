<?php
/**
 * TIGS abstract updater.
 *
 * TIGS uses Edumate, but the basics should be the same as the Synergetic
 * connector, because Edumate uses the same interface as Synergetic.
 *
 * (should extend Synchronise_External_Strategy_Abstract class?)
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
abstract class Synchronise_Edumate_Tigs_Strategy_Abstract extends Synchronise_Edumate_Strategy_Abstract {

	/**
	 * Constructor
	 * Instantiate additional classes
	 */
	public function __construct() {
		parent::__construct();

		//
		// Why bother with separate verbose classes?
		$this->setVerboseFilter(new Synchronise_Edumate_Tigs_Verbose());
	}

	/**
	 * Figure out which campus a student member is in, based on the code provided
	 *
	 * Copy-pasted, should be in base class.
	 *
	 * @param  $code
	 * @return bool|string
	 */
	protected function getCampusFromCode($code) {
		$campus = null;
		switch (strtolower($code)) {
			case 'senior school':
				$campus = 1;
				break;

			case 'junior school':
				$campus = 2;
				break;

			default:
				break;
		}
		return $campus;
	}

	/**
	 * Retrieves the code for the given year level. This is put in the "year"
	 * field of the users table.
	 *
	 * @param string $year_level
	 *
	 * @return string
	 */
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

	/**
	 * Retrieves the group ID for the given year level. This goes in the
	 * group_id field of the users table.
	 *
	 * @param string $year_level
	 *
	 * @return int
	 */
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

	/**
	 * Retrieves the campus ID for the given year level. This goes in the
	 * defaultCampus and campus fields of the users table.
	 *
	 * @param string $year_level
	 *
	 * @return int
	 */
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

	/**
	 * Converts Synergetic StaffCategoryType to admin or teacher group
	 *
	 * @param $category
	 * @return bool|int
	 */
	protected function getGroupIDFromCategory($category) {
		return 1;
	}

	/**
	 * Process the users
	 *
	 */
	public function createIfExists() {
		$this->getVerboseFilter()->intro();

		foreach ($this->getList() as $currentUser) {
			// Generate details about the new user
			$newUser = $this->setupUserDetails($currentUser);
			$this->executeCreateUpdate($newUser);
		}

		$this->getVerboseFilter()->summary();
	}
}
