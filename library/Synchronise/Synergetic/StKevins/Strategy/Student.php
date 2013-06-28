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
		return $currentUser['StudentID'];
	}
}
