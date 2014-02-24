<?php
/**
 * Hydrator for campus for Scs users.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Scs_Hydrator_StaffCampus implements Synchronise_Base_IHydrator {

	/**
     * Hydrates user's campus and default campus.
     */
    public function hydrate($externalUser, $newUser) {
		switch ($externalUser['StaffCampus']) {
			case 'S':
				$campus = 1; break;
			case 'J':
				$campus = 2; break;
			case 'A':
				$campus = 1; break;
			case 'ELC':
				$campus = 3; break;
			default:
				$campus = 1; break;
		}

		$newUser['campus'] = array($campus);
        $newUser['defaultCampus'] = $campus;

        return $newUser;
    }
}
