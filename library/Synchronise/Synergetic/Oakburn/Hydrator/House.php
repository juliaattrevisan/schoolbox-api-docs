<?php
/**
 * Hydrator for house for Oakburn users.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Oakburn_Hydrator_House implements Synchronise_Base_IHydrator {

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
	 * 20  B   Briggs
	 * 21  D   Dean
	 * 22  F   Fox
	 * 23  N   Nance
	 * 24  OA  Oak
	 * 25  TH  Thistle
	 * 26  W   Willow
     *
     * @param  array $externalUser
     * @return int
     */
    public function getHouseFromUser($externalUser) {
        switch (strtoupper(trim($externalUser['StudentHouse']))) {
			case 'B': return 20; break;
			case 'D': return 21; break;
			case 'F': return 22; break;
			case 'N': return 23; break;
			case 'OA': return 24; break;
			case 'TH': return 25; break;
			case 'W': return 26; break;
            default: break;
        }

        return 0;
    }
}
