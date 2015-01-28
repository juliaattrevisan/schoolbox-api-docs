<?php
/**
 * Hydrator for excluding users outside of company code 1.
 *
 * (This is probably going to be common for TASS syncs.)
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Hydrator_ExcludeCompanyCodes implements Synchronise_Base_IHydrator
{

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        if ($externalUser['cmpy_code'] != 1) {
            throw new Exception('Only users in company code 1 are synced');
        }
    }
}
