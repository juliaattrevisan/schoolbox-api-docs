<?php
/**
 * Hydrator for group for Toowoomba staff.
 *
 * Only sets group on update.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Toowoomba_Hydrator_Group_Staff extends Synchronise_Base_Hydrator_CreateUpdate
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

    /**
     * {@inheritdoc}
     */
    public function hydrateOnCreate($externalUser, $newUser)
    {
        $newUser['group_id'] = 2;
        return $newUser;
    }

    /**
     * {@inheritdoc}
     */
    public function hydrateOnUpdate($externalUser, $newUser)
    {
        unset($newUser['group_id']);
        return $newUser;
    }
}
