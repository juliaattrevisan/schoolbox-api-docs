<?php
/**
 * Synchronise_Synergetic_Spags_Strategy_Staff
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Spags_Strategy_Staff extends Synchronise_Base_Strategy_Staff {
    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Synergetic_Spags_Verbose();

        $unsetter = new Synchronise_Base_Hydrator_UnsetOnUpdate();
        $unsetter->setClearFields(array('webmail', 'local_email', 'mail_handler'));

        $this
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Staff())
            ->addHydrator(new Synchronise_Base_Hydrator_Webmail())
            ->addHydrator(new Synchronise_Base_Hydrator_LocalEmail())
            ->addHydrator(new Synchronise_Base_Hydrator_NoMailHandler())
            ->addHydrator(new Synchronise_Synergetic_Spags_Hydrator_Campus_Staff())
            ->addHydrator(new Synchronise_Synergetic_Spags_Hydrator_Group_Staff())
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator($unsetter)
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
