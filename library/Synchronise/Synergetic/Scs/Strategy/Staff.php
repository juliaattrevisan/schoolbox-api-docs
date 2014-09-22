<?php
/**
 * Synchronise_Synergetic_Scs_Strategy_Staff
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Scs_Strategy_Staff extends Synchronise_Base_Strategy_Staff
{
    /**
     * Setup verbose output
     */
    protected function setup()
    {
        $unset = new Synchronise_Base_Hydrator_UnsetOnUpdate();
        $unset->setClearFields(array('webmail', 'eportfolio_enabled'));
        $this->verbose_filter = new Synchronise_Synergetic_Scs_Verbose();
        $this
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Staff())
            ->addHydrator(new Synchronise_Base_Hydrator_Webmail())
            ->addHydrator(new Synchronise_Base_Hydrator_MailHandler())
            ->addHydrator(new Synchronise_Synergetic_Scs_Hydrator_StaffCampus())
            ->addHydrator(new Synchronise_Synergetic_Scs_Hydrator_Group_Staff())
            ->addHydrator($unset)
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
