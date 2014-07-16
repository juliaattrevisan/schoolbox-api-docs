<?php
/**
 * Synchronise_Tass_Cathedral_Strategy_Parent
 *
 * Update Schoolbox with Tass data if there is something different
 * contained in Tass database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Cathedral_Strategy_Parent extends Synchronise_Base_Strategy_Parent {
    protected $title = "Update Schoolbox Parent Details Synchronisation";

    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Tass_Cathedral_Verbose();
        $this
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Parent())
            ->addHydrator(new Synchronise_Tass_Cathedral_Hydrator_Name_Parent())
            ->addHydrator(new Synchronise_Base_Hydrator_BasicMapping(
                'StudentContactID', 'username'
            ))
            ->addHydrator(new Synchronise_Base_Hydrator_BasicMapping(
                'StudentContactID', 'synergy_id', 'P%s'
            ))
            // Use the student campus hydrator, as the student and parent have
            // campus in the same field.
            ->addHydrator(new Synchronise_Tass_Cathedral_Hydrator_Campus_Student())
            ->addHydrator(new Synchronise_Tass_Cathedral_Hydrator_Campus_Parent())
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
            // TASS has no separate spouse entries, so just add a clear one.
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
