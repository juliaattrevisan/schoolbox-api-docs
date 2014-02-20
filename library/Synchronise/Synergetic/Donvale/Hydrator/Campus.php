<?php
/**
 * Hydrator for campus for Donvale users.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Donvale_Hydrator_Campus implements Synchronise_Base_IHydrator {

    /**
     * Hydrates user's campus and default campus.
     *
     * Years 7-12: Senior S (1)
     * Otherwise: Primary P (2)
     */
    public function hydrate($externalUser, $newUser) {
        $campus = null;
        $year = $externalUser['StudentYearLevel'];

        if ($year >= 7 && $year <= 12) {
            $campus = 1;
        }
        else {
            $campus = 2;
        }

		$newUser['campus'] = array($campus);
		$newUser['defaultCampus'] = $campus;

		return $newUser;
    }
}
