<?php
/**
 * Hydrator for name for Meriden staff.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Meriden_Hydrator_Username_Staff implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        // Does this user have an email address? Generate from that if there.
        // Otherwise: try to generate it from the user's initial and surname.
        if (preg_match('/^(.*?)@meriden\\.nsw\\.edu\\.au$/', $externalUser['Email'], $matches)) {
            list(, $newUser['username']) = $matches;
        }
        else {
            $username            = mb_substr($newUser['firstname'], 0, 1, 'UTF-8') . $newUser['lastname'];
            $newUser['username'] = strtolower(preg_replace('/[^a-z0-9]/i', '', iconv('UTF-8', 'ASCII//TRANSLIT', $username)));
        }
        return $newUser;
    }
}
