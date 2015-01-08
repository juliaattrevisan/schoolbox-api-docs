<?php
/**
 * Synchronise_Tass_Meriden_Strategy_Parent
 *
 * Update Schoolbox with Tass data if there is something different
 * contained in Tass database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Tass_Meriden_Strategy_Parent extends Synchronise_Base_Strategy_Parent {
    protected $title = "Update Schoolbox Parent Details Synchronisation";

    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Tass_Meriden_Verbose();
        $this
            // Exclude split families, because original query didn't fetch them
            ->addHydrator(new Synchronise_Tass_Meriden_Hydrator_ExcludeParentAddresses())
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Parent())
            ->addHydrator(new Synchronise_Tass_Meriden_Hydrator_Name_Parent())
            ->addHydrator(new Synchronise_Tass_Meriden_Hydrator_Username_Parent())
            ->addHydrator(new Synchronise_Tass_Meriden_Hydrator_Campus())
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
            // No separate spouse hydrator, so just add a clear one.
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
