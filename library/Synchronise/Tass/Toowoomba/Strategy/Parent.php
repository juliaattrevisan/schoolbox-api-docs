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
            // should be common for all TASS syncs
            ->addHydrator(new Synchronise_Tass_Cathedral_Hydrator_ExcludeNonCurrentParents())
            // should be common for all TASS syncs
            ->addHydrator(new Synchronise_Tass_Cathedral_Hydrator_ExcludeParentAddresses())
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Parent())
            // should be common for all TASS syncs
            ->addHydrator(new Synchronise_Tass_Cathedral_Hydrator_Name_Parent())
            // should be common for all TASS syncs - this reduces emails down to a single mail
            ->addHydrator(new Synchronise_Tass_Cathedral_Hydrator_Email_Parent())
            // username generation is common to all TASS syncs
            ->addHydrator(new Synchronise_Tass_Cathedral_Hydrator_Username_Parent())
            // so is external ID
            ->addHydrator(new Synchronise_Tass_Hydrator_ExternalId_Parent())
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator($unsetOnUpdate)
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
            // TASS has no separate spouse entries, so just add a clear one.
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
