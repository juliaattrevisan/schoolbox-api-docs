<?php
/**
 * Hydrator for Webmail
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Oakburn_Hydrator_Student_Webmail implements Synchronise_Base_IHydrator {

	/**
     * Hydrates user's webmail setting
	 *
	 * SOC have a special conditional webmail
     */
    public function hydrate($externalUser, $newUser) {
		switch ($externalUser['StudentYearLevel']) {
			case '30':
			case '40':
				$newUser['webmail'] = 0;
				break;

			default:
				$newUser['webmail'] = 1;
				break;
		}
		new \Alaress_Probe($newUser);
        return $newUser;
    }
}
