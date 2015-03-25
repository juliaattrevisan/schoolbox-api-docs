<?php
/**
 * Synchronise_Tass_Toowoomba_Strategy_Student
 *
 * Update Schoolbox with Tass data if there is something different
 * contained in Tass database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Toowoomba_Strategy_Student extends Synchronise_Base_Strategy_Student
{
    /**
     * {@inheritdoc}
     */
    protected function setup()
    {
        $this->verbose_filter = new Synchronise_Tass_Toowoomba_Verbose();
        $this
            ->addHydrator(new Synchronise_Tass_Hydrator_ExcludeCompanyCodes())
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Student())
            ->addHydrator(new Synchronise_Base_Hydrator_LocalEmail())
            ->addHydrator(new Synchronise_Tass_Toowoomba_Hydrator_UsernameFromGivenName())
            ->addHydrator(new Synchronise_Tass_Toowoomba_Hydrator_SurnameFromID())
            ->addHydrator(new Synchronise_Tass_Toowoomba_Hydrator_Group_Student())
            ->addHydrator(new Synchronise_Tass_Toowoomba_Hydrator_Campus())
            ->addHydrator(new Synchronise_Tass_Toowoomba_Hydrator_House_Student())
            ->addHydrator(new Synchronise_Tass_Toowoomba_Hydrator_ResidentialHouse_Student())
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
