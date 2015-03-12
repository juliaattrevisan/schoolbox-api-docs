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
            ->addHydrator(new Synchronise_Base_Hydrator_AllowUpdateOnly())
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_Default())
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_Spouse())
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_AllowUpdateOnly())

            /**
             * Custom hydrators
             */

            /* Eportfolio Enabled
             */
            ->addHydrator(new Synchronise_Base_Hydrator_NoEportfolio())
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_NoEportfolio())

            /* Webmail
             */
            ->addHydrator(new Synchronise_Base_Hydrator_NoWebmail())
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_NoWebmail())

            /* External Mail Client
             */
            ->addHydrator(new Synchronise_Base_Hydrator_MailHandler())
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_MailHandler())

            /* Email Address From Username
             */
            ->addHydrator(new Synchronise_Base_Hydrator_NoLocalEmail())
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_NoLocalEmail())

            /* Campus
             */
            ->addHydrator(new Synchronise_Synergetic_Arden_Hydrator_Campus_Parent())
            ->addSpouseHydrator(new Synchronise_Synergetic_Arden_Hydrator_Campus_Parent())

            /* Group
             */
            ->addHydrator(new Synchronise_Synergetic_Arden_Hydrator_Group_Parent())
            ->addSpouseHydrator(new Synchronise_Synergetic_Arden_Hydrator_Group_Parent())

            /**
             * These have to occur at the end
             */
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
