<?php
/**
 * Hydrator for username for Meriden spouses.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Meriden_Hydrator_Username_Spouse implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['synergy_id'] = $newUser['username'] = 'S'.$externalUser['StudentContactID'];
        return $newUser;
    }
}
