<?php
/**
 * Hydrator for username for Toowoomba parents.
 *
 * Currently, usernames are the same as whatever the external ID is set to.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Toowoomba_Hydrator_Username_Parent implements Synchronise_Base_IHydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser['username'] = $newUser['synergy_id'];
        return $newUser;
    }
}
