<?php
/**
 * Hydrator for excluding parent address entries which are not 1 (main contact).
 * (Meriden doesn't use split families?)
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Meriden_Hydrator_ExcludeParentAddresses implements Synchronise_Base_IHydrator
{

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        if ($externalUser['add_num'] != 1) {
            throw new Exception('Parent addresses that are not number 1 are ignored');
        }
    }
}
