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
     * {@inheritdoc}
     */
    public function hydrateOnCreate($externalUser, $newUser)
    {
        $newUser['group_id'] = 2;
        return $newUser;
    }
}
