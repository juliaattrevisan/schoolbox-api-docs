<?php
/**
 * Synchronise_Synergetic_StKevins_Strategy_Staff
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Synergetic_StKevins_Strategy_Staff extends Synchronise_Base_Strategy_Staff {
	protected $title = "Update Schoolbox Staff Details Synchronisation";

	/**
	 * Setup verbose output
	 */
	protected function setup() {
		$this->verbose_filter = new Synchronise_Synergetic_StKevins_Verbose();
	}

	/**
	 * Staff member's username is based on their OccupEmail field.
	 *
	 * @param  array  $currentUser
	 * @return string
	 */
	public function getUsernameFromUser($currentUser) {
		list($username, $domain) = explode('@', $this->getAltEmailFromUser($currentUser));
		return $username;
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
		$campus_code = strtoupper($currentUser['StaffCampus']);
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
	 * Eportfolio is disabled (for now)
	 *
	 * @param  array $currentUser
	 * @return bool
	 */
	public function getEportfolioEnabledFromUser($currentUser) {
		return false;
	}

}
