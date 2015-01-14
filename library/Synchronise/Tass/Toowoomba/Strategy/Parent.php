<?php
/**
 * Synchronise_Tass_Toowoomba_Strategy_Parent
 *
 * Update Schoolbox with Tass data if there is something different
 * contained in Tass database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Toowoomba_Strategy_Parent extends Synchronise_Base_Strategy_Parent
{
    /**
     * {@inheritdoc}
     */
    protected function setup()
    {
        $this->verbose_filter = new Synchronise_Tass_Toowoomba_Verbose();
        $unsetOnUpdate = new Synchronise_Base_Hydrator_UnsetOnUpdate();
        $unsetOnUpdate->setClearFields(array('password'));
        $this
            ->addHydrator(new Synchronise_Tass_Hydrator_ExcludeCompanyCodes())
            ->addHydrator(new Synchronise_Tass_Hydrator_ExcludeNonCurrentParents())
            ->addHydrator(new Synchronise_Tass_Hydrator_ExcludeParentAddresses())
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Parent())
            ->addHydrator(new Synchronise_Tass_Hydrator_Name_Parent())
            ->addHydrator(new Synchronise_Tass_Hydrator_Email_Parent())
            ->addHydrator(new Synchronise_Tass_Hydrator_ExternalId_Parent())
            ->addHydrator(new Synchronise_Tass_Toowoomba_Hydrator_Username_Parent())
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator($unsetOnUpdate)
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
            // TASS has no separate spouse entries, so just add a clear one.
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
