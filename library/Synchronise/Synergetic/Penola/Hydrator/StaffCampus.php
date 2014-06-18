<?php
/**
 * Hydrator for campus for Penola users.
 *
 * @author    Matt Day <matt.day@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Penola_Hydrator_StaffCampus extends Synchronise_Synergetic_Penola_Hydrator_Campus {

	/**
	 * Hydrates user's campus and default campus.
	 */
	public function hydrate($externalUser, $newUser) {
		$campus = $this->getCampusFromCode($externalUser['StaffCampus']);

		$newUser['campus']        = array($campus);
		$newUser['defaultCampus'] = $campus;

		return $newUser;
	}

}
