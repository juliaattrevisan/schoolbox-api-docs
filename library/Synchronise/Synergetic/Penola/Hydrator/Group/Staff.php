<?php
/**
 * Hydrator for group for Penola.
 *
 * @author    Matt Day <matt.day@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Penola_Hydrator_Group_Staff implements Synchronise_Base_IHydrator {

	const ROLE_ADMINISTRATION = 1;
	const ROLE_TEACHERS       = 2;


	/**
	 * {@inheritdoc}
	 */
	public function hydrate($externalUser, $newUser) {
		$newUser['group_id'] = $this->getGroupIDFromUser($externalUser);

		return $newUser;
	}


	/**
	 * Groups for Penola are based on year level
	 *
	 * @param  array $externalUser
	 * @return int
	 */
	public function getGroupIDFromUser($externalUser) {
		$catCode = $externalUser['StaffCategory'];

		$teacherCatCodes = array('LT', 'PRINC');

		if(in_array($catCode, $teacherCatCodes)) {
			return self::ROLE_TEACHERS;
		}

		return self::ROLE_ADMINISTRATION;
	}
}
