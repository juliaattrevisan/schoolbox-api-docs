<?php
/**
 * A hydrator that ensures that a user's username doesn't get changed on
 * update.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_PreserveUsername extends Synchronise_Base_Hydrator_CreateUpdate
{
    /**
     * {@inheritdoc}
     */
    protected function hydrateOnUpdate($externalUser, $newUser)
    {
        $existingUser = $this->userHandler->getBySynergy($newUser['synergy_id']);
        $newUser['username'] = $existingUser['username'];
        return $newUser;
    }
}
