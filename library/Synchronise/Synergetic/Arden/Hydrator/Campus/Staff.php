<?php
/**
 * Hydrator for campus for Arden users.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Arden_Hydrator_Campus_Staff implements Synchronise_Base_IHydrator {

	/**
     * Hydrates user's campus and default campus.
     */
    public function hydrate($externalUser, $newUser) {

        /* Campuses also contain roles for Arden
         */
        switch ($externalUser['StaffCampus']) {

            /* Involved campuses are junior school
             * Set default campus to Year 6
             */
            case 'B':
                $campus = array(1, 2, 3, 4, 5, 6, 7, 8);
                $defaultCampus = 8;
                break;

            /* Involved campuses are high school
             * Set default campus to Year 12
             */
            case 'E':
                $campus = array(9, 10, 11, 12, 13, 14);
                $defaultCampus = 14;
                break;

            /* Empty campus denotes involvement in all campuses
             * Set default campus to Year 6
             */
            default:
                $campus = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14);
                $defaultCampus = 8;
                break;
        }

        $newUser['campus'] = $campus;
        $newUser['defaultCampus'] = $defaultCampus;

        return $newUser;
    }
}
