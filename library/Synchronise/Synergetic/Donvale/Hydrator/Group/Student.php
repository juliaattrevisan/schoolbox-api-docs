<?php
/**
 * Hydrator for group for Donvale.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Donvale_Hydrator_Group_Student implements Synchronise_Base_IHydrator {

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['group_id'] = $this->getGroupIDFromUser($externalUser);

        return $newUser;
    }

    /**
     * Groups for Fintonians are based on year level:
     *
     * * 30 => 3 (ELC)
     * * 40 => 6 (Prep)
     * * Otherwise => year+6
     *
     * @param  array $externalUser
     * @return int
     */
    public function getGroupIDFromUser($externalUser) {
        $year = $externalUser['StudentYearLevel'];

        switch ($year) {
            case 0: return 60;
            case 1: return 59;
			case 2: return 58;
			case 3: return 57;
			case 4: return 56;
			case 5: return 55;
			case 6: return 54;
			case 7: return 3;
			case 8: return 5;
			case 9: return 6;
			case 10: return 7;
			case 11: return 8;
			case 12: return 9;
            default: return 0;
        }
    }
}
