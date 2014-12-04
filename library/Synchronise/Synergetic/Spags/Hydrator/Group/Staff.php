<?php
/**
 * Hydrator for group for Fintonian staff.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Spags_Hydrator_Group_Staff implements Synchronise_Base_IHydrator {

    /**
     * Hydrates groups according to following:
     */
    public function hydrate($externalUser, $newUser) {

        /* Disable account if their category is "Leave"
         */
        if ($externalUser['StaffCategory'] == 'Leave') {
            $newUser['enabled'] = 0;
        }
        /* Otherwise send them into a role
         */
        else {
            switch ($externalUser['StaffCategory']) {
                case 'CRTS': $group = 2; break;
                case 'Non Teaching': $group = 1; break;
                case 'Teaching Senior': $group = 2; break;
                case 'Casual PA': $group = 2; break;
                case 'Teaching Traral': $group = 2; break;
                case 'CRTMT': $group = 2; break;
                case 'Teaching Junior': $group = 2; break;
                case 'Contractor': $group = 2; break;
                case 'CRTT': $group = 2; break;
                case 'CRNTW': $group = 2; break;
                case 'CRTJ': $group = 2; break;
                case 'CRNTT': $group = 2; break;
                case 'Casual': $group = 2; break;
                case 'ELC': $group = 2; break;
                case 'TK': $group = 2; break;

                /* Chuck them into Administration if they're not known
                 */
                default: $group = 1; break;
            }

            $newUser['group_id'] = $group;
        }

        return $newUser;
    }
}
