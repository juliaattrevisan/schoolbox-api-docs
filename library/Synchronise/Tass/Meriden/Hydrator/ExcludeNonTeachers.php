<?php
/**
 * Hydrator for excluding users who aren't teachers.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Meriden_Hydrator_ExcludeNonTeachers implements Synchronise_Base_IHydrator
{

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        if (!isset($externalUser['tch_code'])) {
            throw new Exception('Staff who are not teachers are not synced');
        }
        return $newUser;
    }
}
