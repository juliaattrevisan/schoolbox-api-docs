<?php
/**
 * Hydrator for campus for Donvale users.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Donvale_Hydrator_Campus implements Synchronise_Base_IHydrator {

    /**
     * Hydrates user's campus and default campus.
     *
     * Year level based campuses
     */
    public function hydrate($externalUser, $newUser) {
        $campus = null;
        $year = $externalUser['StudentYearLevel'];

	switch ($year) {
		case "12":
			$campus = '1';
			break;
		case "11":
			$campus = '2';
			break;
		case "10":
			$campus = '3';
			break;
		case "9":
			$campus = '4';
			break;
		case "8":
			$campus = '5';
			break;
		case "7":
			$campus = '6';
			break;
		case "6":
			$campus = '7';
			break;
		case "5":
			$campus = '8';
			break;
		case "4":
			$campus = '9';
			break;
		case "3":
			$campus = '10';
			break;
		case "2":
			$campus = '11';
			break;
		case "1":
			$campus = '12';
			break;
		case "0":
			$campus = '13';
			break;
		default:
			$campus = '1';
	}

		$newUser['campus'] = array($campus);
		$newUser['defaultCampus'] = $campus;

		return $newUser;
    }
}
