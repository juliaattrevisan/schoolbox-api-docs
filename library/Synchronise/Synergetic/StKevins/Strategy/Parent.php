<?php
/**
 * Synchronise_Synergetic_StKevins_Strategy_Parent
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Synergetic_StKevins_Strategy_Parent extends Synchronise_Base_Strategy_Staff {
	protected $title = "Update Schoolbox Parent Details Synchronisation";

	/**
	 * Setup verbose output
	 */
	protected function setup() {
		$this->verbose_filter = new Synchronise_Synergetic_StKevins_Verbose();
	}
}
