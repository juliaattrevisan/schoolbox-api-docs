<?php
/**
 * Hydrator for campus for Penola users.
 *
 * @author    Matt Day <matt.day@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Penola_Hydrator_Campus implements Synchronise_Base_IHydrator {

	const CAMPUS_SENIOR = 1;
	const CAMPUS_JUNIOR = 2;


	/**
	 * Hydrates user's campus and default campus.
	 */
	public function hydrate($externalUser, $newUser) {
		$campus = $this->getCampusFromCode($externalUser['StudentCampus']);

		$newUser['campus']        = array($campus);
		$newUser['defaultCampus'] = $campus;

		return $newUser;
	}


	/**
	 * Detect the appropriate campus from the synergy code given
	 *
	 * @param string $code
	 * @return int
	 */
	public function getCampusFromCode($code) {
		$map = array(
			'J' => self::CAMPUS_JUNIOR,
			'S' => self::CAMPUS_SENIOR
		);

		if(array_key_exists($code, $map)) {
			return $map[$code];
		}

		return 0;
	}
}
