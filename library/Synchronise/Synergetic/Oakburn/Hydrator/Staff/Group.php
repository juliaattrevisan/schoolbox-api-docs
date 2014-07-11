<?php
/**
 * Hydrator for group for Oakburn.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Oakburn_Hydrator_Staff_Group implements Synchronise_Base_IHydrator {

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
		switch ($externalUser['StaffCategory']) {
			case 'T': $group = 3; break;
			case 'RT': $group = 3; break;
			case 'A': $group = 2; break;
			case 'M': $group = 2; break;
			case 'G': $group = 2; break;
			case 'BH': $group = 2; break;
			case 'MU': $group = 2; break;
			case 'D': $group = 2; break;
			case 'L': $group = 2; break;
			case 'Chap': $group = 3; break;

			/* Special campus conditional
			 */
			case 'AN':
				if ($externalUser['StaffCampus'] == 'S') {
					$group = 2;
				}
				else if ($externalUser['StaffCampus'] == 'J') {
					$group = 3;
				}

				break;

			case 'RTA':
			case 'CSS':
			default: $group = 0; break;
		}

		$newUser['group_id'] = $group;

		return $newUser;
    }
}
