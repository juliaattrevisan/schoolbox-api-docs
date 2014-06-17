<?php
/**
 * Hydrator for campus for enazzano users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Genazzano_Hydrator_Campus implements Synchronise_Base_IHydrator {
    /**
     * Hydrates user's campus and default campus.
     *
     * * G - 'Genazzano' (1)
     * * A - 'All Hallows' (2)
     */
    public function hydrate($externalUser, $newUser) {
	    switch (trim($externalUser['StudentCampus'])) {
		    case 'A':
			    $campus = 2; break;
		    case 'G':
		    default:
			    $campus = 1; break;
	    }

	    $newUser['campus'] = array($campus);
	    $newUser['defaultCampus'] = $campus;

	    return $newUser;
    }
}
