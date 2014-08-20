<?php
/**
 * Hydrator for campus for Loyola users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Loyola_Hydrator_Campus implements Synchronise_Base_IHydrator
{
    /**
     * All users are in campus 1.
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser['campus'] = array(1);
        $newUser['defaultCampus'] = 1;
        return $newUser;
    }
}
