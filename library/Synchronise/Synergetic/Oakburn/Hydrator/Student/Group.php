<?php
/**
 * Hydrator for group for Oakburn.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Oakburn_Hydrator_Student_Group implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['group_id'] = $this->getGroupIDFromUser($externalUser);

        return $newUser;
    }

    /**
     * Groups are based on year level:
     *
     * @param  array $externalUser
     * @return int
     */
    public function getGroupIDFromUser($externalUser) {

        if(!(trim($externalUser['StudentCampus']) == 'J' || trim($externalUser['StudentCampus']) == 'S')) {
            //External students put them in the external group
            return 21;
        }

		switch ($externalUser['StudentYearLevel']) {
			case 0: return 8; break;
			case 1: return 9; break;
			case 2: return 10; break;
			case 3: return 11; break;
			case 4: return 12; break;
			case 5: return 13; break;
			case 6: return 14; break;
			case 7: return 15; break;
			case 8: return 16; break;
			case 9: return 17; break;
			case 10: return 18; break;
			case 11: return 19; break;
			case 12: return 20; break;
			case 30: return 6; break;
			case 40: return 7; break;
			default: break;
		}




		return 0;
    }
}
