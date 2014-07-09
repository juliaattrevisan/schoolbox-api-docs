<?php
/**
 * Synchronise_Tass_Cathedral_Strategy_Staff
 *
 * Update Schoolbox with Tass data if there is something different
 * contained in Tass database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Cathedral_Strategy_Staff extends Synchronise_Base_Strategy_Staff {

    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Tass_Cathedral_Verbose();
        $unsetOnUpdate = new Synchronise_Base_Hydrator_UnsetOnUpdate();
        $unsetOnUpdate->setClearFields(array('campus', 'defaultCampus', 'group_id'));
        $this
            ->addHydrator(new Synchronise_Base_Hydrator_Staff())
            ->addHydrator(new Synchronise_Base_Hydrator_Staff_UsernameFromId())
            ->addHydrator(new Synchronise_Tass_Cathedral_Hydrator_Campus_Staff())
            ->addHydrator(new Synchronise_Tass_Cathedral_Hydrator_Group_Staff())
            ->addHydrator($unsetOnUpdate)
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
