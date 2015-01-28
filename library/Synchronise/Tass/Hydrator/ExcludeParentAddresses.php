<?php
/**
 * Hydrator for excluding parent address entries which are not 1 (main contact)
 * or 4 (separated parent contact).
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Hydrator_ExcludeParentAddresses implements Synchronise_Base_IHydrator
{

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        if (!in_array($externalUser['add_num'], array(1, 4))) {
            throw new Exception('Parent addresses that are not number 1 or 4 are ignored');
        }
    }
}
