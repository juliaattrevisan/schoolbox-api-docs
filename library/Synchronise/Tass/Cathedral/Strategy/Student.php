<?php
/**
 * Synchronise_Tass_Cathedral_Strategy_Student
 *
 * Update Schoolbox with Tass data if there is something different
 * contained in Tass database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Cathedral_Strategy_Student extends Synchronise_Base_Strategy_Student
{
    /**
     * Setup verbose output and hydrator classes.
     */
    protected function setup()
    {
        $this->verbose_filter = new Synchronise_Tass_Cathedral_Verbose();
        $this
            ->addHydrator(new Synchronise_Tass_Hydrator_ExcludeCompanyCodes())
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Student())
            ->addHydrator(new Synchronise_Tass_Cathedral_Hydrator_Campus_Student())
            ->addHydrator(new Synchronise_Tass_Cathedral_Hydrator_Group_Student())
            ->addHydrator(new Synchronise_Base_Hydrator_Student_UsernameFromEmail())
            ->addHydrator(new Synchronise_Base_Hydrator_LocalEmail())
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
