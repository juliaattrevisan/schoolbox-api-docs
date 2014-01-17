<?php
/**
 * Synchronise_Tass_Meriden_Strategy_Parent
 *
 * Update Schoolbox with Tass data if there is something different
 * contained in Tass database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Tass_Meriden_Strategy_Parent extends Synchronise_Base_Strategy_Parent {
	protected $title = "Update Schoolbox Parent Details Synchronisation";

	/**
	 * Setup verbose output
	 */
	protected function setup() {
		$this->verbose_filter = new Synchronise_Tass_Meriden_Verbose();
	}

	/**
	 * St. Kevins parents have local email on.
	 *
	 * @param array $currentUser
	 *
	 * @return int
	 */
	public function getLocalEmailFromUser($currentUser) {
		return 1;
	}
}
