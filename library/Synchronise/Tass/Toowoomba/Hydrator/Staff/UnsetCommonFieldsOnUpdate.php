<?php
/**
 * Toowoomba staff specific implementation of unset common fields,
 * because their staff have a custom userExists method.
 *
 * This will be removed once I've made these classes use the strategy's
 * userExists method.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Toowoomba_Hydrator_Staff_UnsetCommonFieldsOnUpdate
extends Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate
{
    /**
     * Overridden to only search external ID because I can't accurately
     * determine the role of a staff member.
     *
     * TODO: make these CreateUpdate classes use strategy's userExists method
     *
     * {@inheritdoc}
     */
    protected function userExists($externalUser, $newUser)
    {
        return $this->userHandler->getBySynergy($newUser['synergy_id']);
    }
}
