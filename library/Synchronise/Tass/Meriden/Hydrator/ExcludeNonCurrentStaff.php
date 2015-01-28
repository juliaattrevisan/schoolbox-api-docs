<?php
/**
 * Hydrator for excluding users who have a termination date.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Meriden_Hydrator_ExcludeNonCurrentStaff implements Synchronise_Base_IHydrator
{

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        if ($externalUser['tch_code'] != 'Y') {
            throw new Exception('Employees who are no longer at school are not synced');
        }
        return $newUser;
    }
}
