<?php
/**
 * Hydrator for house for Penola users.
 *
 * @author    Matt Day <matt.day@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Penola_Hydrator_House implements Synchronise_Base_IHydrator {

	const HOUSE_GEOGHEGAN     = 1;
	const HOUSE_MACKILLOP     = 2;
	const HOUSE_NOLAN         = 3;
	const HOUSE_SANCTA_SOPHIA = 4;
	const HOUSE_SMYTH         = 5;
	const HOUSE_THERRY        = 6;


	/**
	 * {@inheritdoc}
	 */
	public function hydrate($externalUser, $newUser) {
		$newUser['house'] = $this->getHouseFromUser($externalUser);

		return $newUser;
	}


	/**
	 * Gets house according to the following map:
	 *
	 * @param  array $externalUser
	 * @return int
	 */
	public function getHouseFromUser($externalUser) {

		$houseCode = strtoupper(trim($externalUser['StudentHouse']));

		$map = array(
			'GEGN' => self::HOUSE_GEOGHEGAN,
			'MACP' => self::HOUSE_MACKILLOP,
			'NOLN' => self::HOUSE_NOLAN,
			'SASO' => self::HOUSE_SANCTA_SOPHIA,
			'SMYT' => self::HOUSE_SMYTH,
			'THRY' => self::HOUSE_THERRY
		);

		if(array_key_exists($houseCode, $map)) {
			return $map[$houseCode];
		}

		return 0;
	}
}
