<?php
/**
 * Synchronise_Synergetic_Tintern_Strategy_Parent
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Synergetic_Tintern_Strategy_Parent extends Synchronise_Base_Strategy_Parent {
    protected $title = "Update Schoolbox Parent Details Synchronisation";

    /**
     * Used to retrieve student campus out of each parent.
     *
     * @var Synchronise_Synergetic_Tintern_Strategy_Student
     */
    protected $student_strategy;

    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter   = new Synchronise_Synergetic_Tintern_Verbose();
        $this->student_strategy = new Synchronise_Synergetic_Tintern_Strategy_Student();
    }

    /**
     * Gets default campus in same method as it's retrieved from student.
     *
     * @param  array $currentUser
     * @return int
     */
    public function getDefaultCampusFromUser($currentUser) {
        return $this->student_strategy->getDefaultCampusFromUser($currentUser);
    }

    /**
     * Use this method to determine if the parent sync should continue - by
     * default, parents are not created if they don't already exist.
     *
     * @param  $currentUser
     * @return bool
     */
    protected function hasUsername($currentUser) {
        if (!($this->userExists($currentUser))) {
            return false;
        }

        return strlen(trim($currentUser['username'])) > 0;
    }

	/**
	 * Webmail setting
	 *
	 * @param $currentUser
	 * @return int|void
	 */
	public function getWebmailFromUser($currentUser) {
		return 0;
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
	 * Eportfolio Enabled setting
	 *
	 * @param array $currentUser
	 * @return bool|int
	 */
	public function getEportfolioEnabledFromUser($currentUser) {
		return 0;
	}
}
