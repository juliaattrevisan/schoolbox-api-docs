<?php
/**
 * Hydrator for campus for Spags users.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Spags_Hydrator_Campus_Student implements Synchronise_Base_IHydrator {

    /**
     * Hydrates user's campus and default campus.
     *
     * * Years 5-12: Senior (1)
     * * Otherwise:  Junior (2)
     */
    public function hydrate($externalUser, $newUser) {
        switch ($externalUser['StudentCampus']) {
            case 'J':
                $campus = 3;
                break;
            case 'M':
                $campus = 1;
                break;
            case 'S':
                $campus = 1;
                break;
            case 'T':
                $campus = 4;
                break;
            case 'TS':
                $campus = 2;
                break;
            case 'YR9':
                $campus = 1;
                break;
            case 'TK4':
                $campus = 4;
                break;
            case 'TK3':
                $campus = 4;
                break;
            case 'EL4':
                $campus = 3;
                break;
            case 'EL3':
                $campus = 3;
                break;
            default:
                $campus = 1;
                break;
        }

        $newUser['campus']        = array($campus);
        $newUser['defaultCampus'] = $campus;

        return $newUser;
    }
}
