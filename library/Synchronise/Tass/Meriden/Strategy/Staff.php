<?php
/**
 * Synchronise_Tass_Meriden_Strategy_Staff
 *
 * Update Schoolbox with Tass data if there is something different
 * contained in Tass database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Tass_Meriden_Strategy_Staff extends Synchronise_Base_Strategy_Staff {
    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Tass_Meriden_Verbose();
        $this
            ->addHydrator(new Synchronise_Tass_Meriden_Hydrator_ExcludeNonCurrentStaff())
            ->addHydrator(new Synchronise_Tass_Meriden_Hydrator_ExcludeNonTeachers())
            ->addHydrator(new Synchronise_Base_Hydrator_Staff())
            ->addHydrator(new Synchronise_Tass_Meriden_Hydrator_Name_Staff())
            ->addHydrator(new Synchronise_Tass_Meriden_Hydrator_Username_Staff())
            ->addHydrator(new Synchronise_Tass_Meriden_Hydrator_Campus_Staff())
            ->addHydrator(new Synchronise_Tass_Meriden_Hydrator_Group_Staff())
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
