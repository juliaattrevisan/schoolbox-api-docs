<?php
/**
 * Synchronise_Synergetic_Fintona_Strategy_Student
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_ARden_Strategy_Student extends Synchronise_Base_Strategy_Student {
    /**
     * Setup verbose output and hydrator classes.
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Synergetic_Fintona_Verbose();
        $this

            /**
             * Default hydrators
             */
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Student())

            /**
             * Custom hydrators
             */
            ->addHydrator(new Synchronise_Synergetic_Arden_Hydrator_Campus_Student())
            ->addHydrator(new Synchronise_Synergetic_Arden_Hydrator_Group_Student())
            ->addHydrator(new Synchronise_Synergetic_Arden_Hydrator_House_Student())
            ->addHydrator(new Synchronise_Synergetic_Arden_Hydrator_Year_Student())

            /**
             * These have to occur at the end
             */
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
