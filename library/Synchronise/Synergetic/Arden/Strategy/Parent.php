<?php
/**
 * Synchronise_Synergetic_Arden_Strategy_Parent
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Arden_Strategy_Parent extends Synchronise_Base_Strategy_Parent {
    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Synergetic_Arden_Verbose();
        $this

            /**
             * Default hydrators
             */
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Parent())
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_Default())
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_Spouse())

            /**
             * Custom hydrators
             */
            ->addHydrator(new Synchronise_Synergetic_Arden_Hydrator_Campus_Parent())
            ->addSpouseHydrator(new Synchronise_Synergetic_Arden_Hydrator_Campus_Parent())

            /**
             * These have to occur at the end
             */
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
