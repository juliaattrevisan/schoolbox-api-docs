<?php
/**
 * Hydrator for username for Cathedral parents.
 *
 * This is exactly the same as the Meriden parent username sync, but with
 * usernames for single parents (add_num=4) suffixed with "-4".
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
        $externalId = 'P'.$externalUser['StudentContactID'];
        if ($externalUser['add_num'] == 4) {
            $externalId .= '-4';
        }
        $newUser['username'] = $externalId;
        return $newUser;
    }
}
