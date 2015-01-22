<?php
/**
 * Synchronise_Tass_Toowoomba_Strategy_Staff
 *
 * Update Schoolbox with Tass data if there is something different
 * contained in Tass database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Toowoomba_Strategy_Staff extends Synchronise_Base_Strategy_Staff
{
    /**
     * Overridden to only search external ID because I can't accurately
     * determine the role of a staff member.
     *
     * {@inheritdoc}
     */
    protected function userExists($user)
    {
        return $this->user_handler->getBySynergy($user['synergy_id']);
    }

    /**
     * {@inheritdoc}
     */
    protected function setup()
    {
        $this->verbose_filter = new Synchronise_Tass_Toowoomba_Verbose();
        $this
            ->addHydrator(new Synchronise_Tass_Hydrator_ExcludeCompanyCodes())
            ->addHydrator(new Synchronise_Tass_Hydrator_ExcludeTerminated())
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Staff())
            ->addHydrator(new Synchronise_Tass_Toowoomba_Hydrator_Username_Staff())
            // Don't override usernames
            ->addHydrator(new Synchronise_Tass_Toowoomba_Hydrator_PreserveUsername())
            ->addHydrator(new Synchronise_Tass_Toowoomba_Hydrator_Group_Staff())
            ->addHydrator(new Synchronise_Tass_Toowoomba_Hydrator_Campus())
            // Email address comes from username
            ->addHydrator(new Synchronise_Base_Hydrator_LocalEmail())
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
