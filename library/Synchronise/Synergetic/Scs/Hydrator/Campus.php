<?php
/**
 * Hydrator for campus for Fintona users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Fintona_Hydrator_Campus implements Synchronise_Base_IHydrator {

	/**
     * Hydrates user's campus and default campus.
     */
    public function hydrate($externalUser, $newUser) {
		switch ($externalUser['StudentCampus']) {
			case 'S':
				$campus = 1; break;
			case 'J':
				$campus = 2; break;
			default:
				$campus = 3; break;
		}

		$newUser['campus'] = array($campus);
        $newUser['defaultCampus'] = $campus;

        return $newUser;
    }
}
