<?php
/**
 * Synchronise_Synergetic_Spags_Strategy_Student
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Spags_Strategy_Student extends Synchronise_Base_Strategy_Student {

    /**
     * Setup verbose output and hydrator classes.
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Synergetic_Spags_Verbose();

        $unsetter = new Synchronise_Base_Hydrator_UnsetOnUpdate();
        $unsetter->setClearFields(array('webmail', 'local_email', 'mail_handler'));

        $this
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Student())
            ->addHydrator(new Synchronise_Base_Hydrator_Webmail())
            ->addHydrator(new Synchronise_Base_Hydrator_LocalEmail())
            ->addHydrator(new Synchronise_Base_Hydrator_NoMailHandler())
            ->addHydrator(new Synchronise_Synergetic_Spags_Hydrator_House())
            ->addHydrator(new Synchronise_Synergetic_Spags_Hydrator_Campus_Student())
            ->addHydrator(new Synchronise_Synergetic_Spags_Hydrator_Group_Student())
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator($unsetter)
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
        ;

    }
}
