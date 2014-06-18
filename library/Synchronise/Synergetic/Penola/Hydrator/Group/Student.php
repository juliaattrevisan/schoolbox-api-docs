<?php
/**
 * Hydrator for group for Penola.
 *
 * @author    Matt Day <matt.day@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Penola_Hydrator_Group_Student implements Synchronise_Base_IHydrator {

	const ROLE_SENIOR_STUDENTS = 3;
	const ROLE_JUNIOR_STUDENTS = 5;


	/**
	 * {@inheritdoc}
	 */
	public function hydrate($externalUser, $newUser) {
		$newUser['group_id'] = $this->getGroupIDFromUserCampus($externalUser);

		return $newUser;
	}


	/**
	 * Groups for Penola are based on year level
	 *
	 * @param  array $externalUser
	 * @return int
	 */
	public function getGroupIDFromUserCampus($externalUser) {
		$campusCode = strtoupper(trim($externalUser['StudentCampus']));

		$map = array(
			'J' => self::ROLE_JUNIOR_STUDENTS,
			'S' => self::ROLE_SENIOR_STUDENTS
		);

		if(array_key_exists($campusCode, $map)) {
			return $map[$campusCode];
		}

		return 0;
	}
}
