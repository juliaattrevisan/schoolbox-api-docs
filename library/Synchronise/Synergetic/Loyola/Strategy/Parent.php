<?php
/**
 * Synchronise_Synergetic_Loyola_Strategy_Parent
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Loyola_Strategy_Parent extends Synchronise_Base_Strategy_Parent
{
    /**
     * Setup verbose output
     */
    protected function setup()
    {
        // Syncs parents
        // How do I make sure that only updates go ahead?
        $this->verbose_filter = new Synchronise_Synergetic_Loyola_Verbose();
        $this
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Parent())
            ->addHydrator(new Synchronise_Synergetic_Loyola_Hydrator_Group_Parent())
            ->addHydrator(new Synchronise_Synergetic_Loyola_Hydrator_Campus())
            ->addHydrator(new Synchronise_Base_Hydrator_AllowUpdateOnly())
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_Default())
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_Spouse())
            ->addSpouseHydrator(new Synchronise_Synergetic_Loyola_Hydrator_Group_Parent())
            ->addSpouseHydrator(new Synchronise_Synergetic_Loyola_Hydrator_Campus())
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_AllowUpdateOnly())
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
