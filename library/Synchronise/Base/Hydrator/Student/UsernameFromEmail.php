<?php
/**
 * Hydrates staff member's username from their email address.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_Student_UsernameFromEmail implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['username'] = preg_replace('/@.*$/', '', $externalUser['StudentEmail']);
        return $newUser;
    }
}
