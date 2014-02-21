<?php
/**
 * Synchronise_Synergetic_Donvale_Strategy_Student
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Donvale_Strategy_Student extends Synchronise_Base_Strategy_Student {

    /**
     * Setup verbose output and hydrator classes.
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Synergetic_Donvale_Verbose();

        $this
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Student())
			->addHydrator(new Synchronise_Synergetic_Donvale_Hydrator_NoWebmail())
            ->addHydrator(new Synchronise_Base_Hydrator_Student_UsernameFromId())
            ->addHydrator(new Synchronise_Synergetic_Donvale_Hydrator_House())
            ->addHydrator(new Synchronise_Synergetic_Donvale_Hydrator_Campus())
            ->addHydrator(new Synchronise_Synergetic_Donvale_Hydrator_Group_Student())
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
