<?php
/**
 * Hydrator for group for Fintonian staff.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Fintona_Hydrator_Group_Staff implements Synchronise_Base_IHydrator {
    /**
     * Hydrates groups according to following:
     * * TEACHER => 2
     * * STAFF   => 19
     * * default => 19
     */
    public function hydrate($externalUser, $newUser) {
        $group = 19;
        switch ($externalUser['StaffCategoryType']) {
            case 'TEACHER':
                $group = 2;
                break;
            case 'STAFF':
            default:
                break;
        }
        $newUser['group_id'] = $group;
        return $newUser;
    }
}
