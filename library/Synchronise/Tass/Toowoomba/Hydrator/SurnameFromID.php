<?php
/**
 * Puts a students ID in their lastname ??#$#^^#
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Toowoomba_Hydrator_SurnameFromID implements Synchronise_Base_IHydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser['lastname'] = $newUser['lastname'].' ('.$newUser['synergy_id'].')';
        return $newUser;
    }
}
