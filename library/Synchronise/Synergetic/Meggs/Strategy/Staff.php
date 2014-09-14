<?php
/**
 * Synchronise_Synergetic_Meggs_Strategy_Staff
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Meggs_Strategy_Staff extends Synchronise_Base_Strategy_Staff
{
    /**
     * Setup verbose output
     */
    protected function setup()
    {
        $this->verbose_filter = new Synchronise_Synergetic_Meggs_Verbose();
        $this
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Staff())
//            ->addHydrator(new Synchronise_Base_Hydrator_AllCampus())
            ->addHydrator(new Synchronise_Base_Hydrator_BasicValue('local_email', false))
            ->addHydrator(new Synchronise_Base_Hydrator_BasicValue('mail_handler', false))
            ->addHydrator(new Synchronise_Base_Hydrator_BasicValue('webmail', true))
//            ->addHydrator(new Synchronise_Synergetic_Meggs_Hydrator_Staff_Campus())
//            ->addHydrator(new Synchronise_Synergetic_Meggs_Hydrator_Staff_Eportfolio())
//            ->addHydrator(new Synchronise_Synergetic_Meggs_Hydrator_StaffDefaultEmail())
            ->addHydrator(new Synchronise_Base_Hydrator_Webmail())
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
