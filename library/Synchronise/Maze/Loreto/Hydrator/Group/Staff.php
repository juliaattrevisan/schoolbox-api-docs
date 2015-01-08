<?php
/**
 * Hydrator for group for Loretions.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Maze_Loreto_Hydrator_Group_Staff implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['group_id'] = $this->getGroupIDFromUser($externalUser);
        return $newUser;
    }

    /**
     * Groups for Loretions are based on year level:
     *
     * @param  array $externalUser
     * @return int
     */
    public function getGroupIDFromUser($externalUser) {
		$type = $externalUser['ActiveFlag'];

		switch($type) {

			/* Support staff are their own special group
			 */
			case 'N': return 12; break;

			/* Everyone else is in group 2
			 */
			default: return 2; break;
		}
    }
}
