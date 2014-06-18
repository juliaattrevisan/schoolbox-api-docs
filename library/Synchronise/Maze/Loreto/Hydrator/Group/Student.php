<?php
/**
 * Hydrator for group for Loretions.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Maze_Loreto_Hydrator_Group_Student implements Synchronise_Base_IHydrator {
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
     * @param  array $externalUser
     * @return int
     */
    public function getGroupIDFromUser($externalUser) {
        $year = $externalUser['StudentYearLevel'];

        switch ($year) {

			/* Year 1-6 incl.
			 */
			case 1: return 14; break;
			case 2: return 15; break;
			case 3: return 16; break;
			case 4: return 17; break;
			case 5: return 18; break;
			case 6: return 19; break;

			/* Year 7-12 incl.
			 */
			case 7: return 6; break;
			case 8: return 7; break;
			case 9: return 8; break;
			case 10: return 9; break;
			case 11: return 10; break;
			case 12: return 11; break;

			/* Everything else is presumed Kinder
			 */
			default: return 13; break;
        }
    }
}
