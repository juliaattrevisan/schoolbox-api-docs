<?php
/**
 * Hydrator for house for Loreto users.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Maze_Loreto_Hydrator_House implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['house'] = $this->getHouseFromUser($externalUser);
        return $newUser;
    }

    /**
     * Gets house according to the following map:
     *
	 * BARR => Barry (1)
	 * HEAT => Heaton (2)
	 * MILS => Milson (3)
	 * WARD => Ward (4)
	 *
     * @param  array $externalUser
     * @return int
     */
    public function getHouseFromUser($externalUser) {
        switch (strtoupper(trim($externalUser['StudentHouse']))) {
            case 'BARR':
                return 1;
            case 'HEAT':
                return 2;
            case 'MILS':
                return 3;
            case 'WARD':
                return 4;
            default:
                break;
        }
        return 0;
    }
}
