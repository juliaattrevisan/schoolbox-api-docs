<?php
/**
 * Synchronise_Synergetic_Arden_Strategy_Staff
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Arden_Strategy_Staff extends Synchronise_Base_Strategy_Staff {

    /**
     * Setup verbose output and hydrator classes.
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Synergetic_Arden_Verbose();
        $this

            /**
             * Default decorators
             */
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Staff())
            ->addHydrator(new Synchronise_Base_Hydrator_AllCampus())

            /**
             * Optional base decorators
             */

            /* Eportfolio Enabled
             */
            ->addHydrator(new Synchronise_Base_Hydrator_NoEportfolio())

            /* Webmail Tab
             */
            ->addHydrator(new Synchronise_Base_Hydrator_Webmail())

            /* Use External Mail Client
             */
            ->addHydrator(new Synchronise_Base_Hydrator_NoMailHandler())

            /* Email Address From Username
             */
            ->addHydrator(new Synchronise_Base_Hydrator_LocalEmail())

            /**
             * Custom school-specific decorators
             */

            ->addHydrator(new Synchronise_Synergetic_Arden_Hydrator_Group_Staff())
            ->addHydrator(new Synchronise_Synergetic_Arden_Hydrator_Campus_Staff())

            /* Beecroft staff should have no alt_email
             */
            ->addHydrator(new Synchronise_Synergetic_Arden_Hydrator_NoBeecroftEmail())

            /* These always have to appear at the end
             */
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
