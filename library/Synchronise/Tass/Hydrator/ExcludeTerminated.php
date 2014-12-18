<?php
/**
 * Hydrator for excluding staff with a termination date
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Hydrator_ExcludeTerminated implements Synchronise_Base_IHydrator
{

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        if ($externalUser['term_date']) {
            throw new Exception('Only current users are synced');
        }
    }
}
