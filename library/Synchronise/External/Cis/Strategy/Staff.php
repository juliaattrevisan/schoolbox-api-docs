<?php
/**
 * Synchronise_Schoolbox_Cis_Strategy_Staff
 *
 * Update Schoolbox with Schoolbox data if there is something different
 * contained in Schoolbox database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_External_Cis_Strategy_Staff extends Synchronise_Base_Strategy_Staff {

    protected $location = '';
    protected $columns  = array();
    protected $title    = "Update Schoolbox Staff Details Synchronisation";

    /**
     * Creates a list from the XML reader.
     */
    public function generateList()
    {
        $reader = new XMLReader();
        $reader->open(SITE_root.'/staff.xml');
        $iterator   = new External_Isams_Batch_Reader_Staff($reader);
        $this->list = $iterator->getAccountInfo();
    }

    /**
     * Setup verbose output
     */
    protected function setup()
    {
        $this->verbose_filter = new Synchronise_External_Cis_Verbose();
        $this
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Staff())
            ->addHydrator(new Synchronise_Base_Hydrator_BasicMapping('ID', 'external_id', 'T-%'))
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
