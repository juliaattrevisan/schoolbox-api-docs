<?php
/**
 * Hydrator for campus for Fintona users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Fintona_Hydrator_Campus implements Synchronise_Base_IHydrator {
    /**
     * Hydrates user's campus and default campus.
     *
     * * Years 5-12: Senior (1)
     * * Otherwise:  Junior (2)
     */
    public function hydrate($externalUser, $newUser) {
        $campus = null;
        $year   = $externalUser['StudentYearLevel'];

        // Year 5-12 is usually senior campus,
        if ($year >= 5 && $year <= 12) {
            $campus = 1;
        }
        else {
            $campus = 2;
        }
        $newUser['campus']        = array($campus);
        $newUser['defaultCampus'] = $campus;
        return $newUser;
    }
}
