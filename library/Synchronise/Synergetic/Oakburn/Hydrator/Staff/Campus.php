<?php
/**
 * Hydrator for campus for Oakburn users.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Oakburn_Hydrator_Staff_Campus extends Synchronise_Base_Hydrator_CreateUpdate implements Synchronise_Base_IHydrator {

	/**
     * Hydrates user's campus and default campus.
     */
    public function hydrateOnCreate($externalUser, $newUser) {

        switch(trim($externalUser['StaffCampus'])) {
			case 'S': $campus = 1; break;
			case 'J': $campus = 3; break;
			default: $campus = 4; break;
		}

        $newUser['campus'] = array($campus);
        $newUser['defaultCampus'] = $campus;

        return $newUser;
    }

}
