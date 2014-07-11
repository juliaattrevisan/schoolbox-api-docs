<?php
/**
 * Hydrator for username for Cathedral parents.
 *
 * This is exactly the same as the Meriden parent username sync.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Cathedral_Hydrator_Username_Parent implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['synergy_id'] = $newUser['username'] = 'P'.$externalUser['StudentContactID'];
        return $newUser;
    }
}
