<?php
/**
 * Hydrator for campus for Arden users.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Arden_Hydrator_Campus_Parent implements Synchronise_Base_IHydrator {

	/**
     * Hydrates user's campus and default campus.
     */
    public function hydrate($externalUser, $newUser) {

        switch($externalUser['StudentYearLevel']) {
            case -1:
                $campus = 1;
                break;

            case 0:
                $campus = 2;
                break;

            case 1:
                $campus = 3;
                break;

            case 2:
                $campus = 4;
                break;

            case 3:
                $campus = 5;
                break;

            case 4:
                $campus = 6;
                break;

            case 5:
                $campus = 7;
                break;

            case 6:
                $campus = 8;
                break;

            case 7:
                $campus = 9;
                break;

            case 8:
                $campus = 10;
                break;

            case 9:
                $campus = 11;
                break;

            case 10:
                $campus = 12;
                break;

            case 11:
                $campus = 13;
                break;

            case 12:
                $campus = 14;
                break;

            default:
                $campus = null;
                break;
        }

        /* Accumulate
         */
        $newUser['campus'] = array($campus);

        /* Default campus is the oldest child's campus
         */
        $newUser['defaultCampus'] = max($newUser['campus']);

        return $newUser;
    }
}
