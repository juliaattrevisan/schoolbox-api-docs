<?php
/**
 * Hydrator for campus for Donvale users.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Donvale_Hydrator_StaffCampus implements Synchronise_Base_IHydrator {

    /**
     * Hydrates user's campus and default campus.
     *
     * Years 7-12: Senior S (1)
     * Otherwise: Primary P (7)
     */
    public function hydrate($externalUser, $newUser) {

		/* Sometimes campus comes through as either P or empty string
		 * In latter case, assume P
		 */
		if ($externalUser['StaffCampus'] == 'S') {
			$campus = 1;
			$newUser['campus'] = array('1','2','3','4','5','6');
		}
		else {
			$campus = '7';
			$newUser['campus'] = array('7','8','9','10','11','12','13');
		}

		$newUser['defaultCampus'] = $campus;

		return $newUser;
    }
}
