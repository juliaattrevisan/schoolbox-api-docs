<?php
/**
 * A hydrator that ensures that a user's username doesn't get changed on
 * update.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Toowoomba_Hydrator_PreserveUsername extends Synchronise_Base_Hydrator_PreserveUsername
{
    /**
     * Overridden to only search external ID because I can't accurately
     * determine the role of a staff member.
     *
     * TODO: make these CreateUpdate classes use hydrator's userExists method
     *
     * {@inheritdoc}
     */
    protected function userExists($externalUser, $newUser)
    {
        return $this->userHandler->getBySynergy($newUser['synergy_id']);
    }
}
