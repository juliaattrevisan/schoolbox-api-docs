<?php
/**
 * Hydrator for campus for Penola users.
 *
 * @author    Matt Day <matt.day@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Penola_Hydrator_NoAltMail implements Synchronise_Base_IHydrator {

	/**
	 * Hydrates user's campus and default campus.
	 */
	public function hydrate($externalUser, $newUser) {
		$newUser['alt_email'] = '';

		return $newUser;
	}

}
