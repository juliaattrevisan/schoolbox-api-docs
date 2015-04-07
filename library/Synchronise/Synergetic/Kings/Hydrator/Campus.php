<?php
/**
 * Hydrator for campus for Kings users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Kings_Hydrator_Campus implements Synchronise_Base_IHydrator {
    /**
     * Hydrates user's campus and default campus.
     *
     * * G - 'Genazzano' (1)
     * * A - 'All Hallows' (2)
     */
    public function hydrate($externalUser, $newUser) {
		 $studentCampus = trim($externalUser['StudentCampus']);
		 
		 if ($studentCampus == 'T') {
		 	//$campus = 4;
		 	$campus = 0;
		 	$newUser['username'] = null;
		 		//throw new exception('Skip Tudor House Students.');
		 } else {
		  	if ($externalUser['StudentYearLevel'] < 7) {
		 		$campus = 3;
		 	} else {
		 		$campus = 1;
		 	}
		 }		 	
		 
	    $newUser['campus'] = array($campus);
	    $newUser['defaultCampus'] = $campus;

	    return $newUser;
    }
}
