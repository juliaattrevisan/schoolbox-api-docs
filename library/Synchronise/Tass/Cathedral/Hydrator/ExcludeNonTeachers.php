<?php
/**
 * Hydrator for excluding users who aren't teachers.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Cathedral_Hydrator_ExcludeNonTeachers implements Synchronise_Base_IHydrator {

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        switch ($externalUser['awd_code']) {
            case "TE":
            case "TECAS":
            case "EX":
                return $newUser;

            default:
                throw new Exception('Staff who are not teachers are not synced');
        }
    }
}
