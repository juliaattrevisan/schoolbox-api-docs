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
	 * Figure out the Group ID based on a Student's year level
	 *
	 * Copy-pasted, should be in base class.
	 *
	 * @param  $year
	 * @return bool|int
	 */
	protected function getGroupIDFromYearLevel($year) {
		return 1;
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
