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
            case -2:
                $campus = 1;
                break;

            case -1:
                $campus = 1;
                break;

            case 0:
                $campus = 15;
                break;

            case 1:
                $campus = 15;
                break;

            case 2:
                $campus = 15;
                break;

            case 3:
                $campus = 15;
                break;

            case 4:
                $campus = 15;
                break;

            case 5:
                $campus = 15;
                break;

            case 6:
                $campus = 15;
                break;

            case 7:
                $campus = 16;
                break;

            case 8:
                $campus = 16;
                break;

            case 9:
                $campus = 16;
                break;

            case 10:
                $campus = 17;
                break;

            case 11:
                $campus = 17;
                break;

            case 12:
                $campus = 17;
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
