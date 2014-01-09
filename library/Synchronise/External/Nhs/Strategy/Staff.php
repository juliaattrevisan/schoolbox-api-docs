<?php
/**
 * Synchronise_Schoolbox_Nhs_Strategy_Staff
 *
 * Update Schoolbox with Schoolbox data if there is something different
 * contained in Schoolbox database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_External_Nhs_Strategy_Staff extends Synchronise_Base_Strategy_Staff {

    protected $location = '';
    protected $columns  = array();
    protected $title    = "Update Schoolbox Staff Details Synchronisation";

    /**
     * Sets up synchronisation with parameters passed from the definition.
     *
     * @param string $location Where the CSV for import is located
     * @param array  $columns  The columns to import
     */
    public function __construct($location, $columns) {
        parent::__construct();
        $this->location = $location;
        $this->columns  = $columns;
    }

    /**
     * Creates a list from a CSV file instead of from a DB connection
     */
    public function generateList() {
        $this->list = array();
        $reader = new Alaress\Csv\Reader($this->location);
        $reader->setColumnList($this->columns);
        foreach ($reader as $row) {
            $this->list[] = $row;
        }
    }

    /**
     * Until further notice, there are no titles.
     *
     * @param  array $currentUser
     * @return string
     */
    public function getTitleFromUser($currentUser) {
        return '';
    }

    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_External_Nhs_Verbose();
    }

    /**
     * Returns 1 until further notice.
     *
     * @param  array $currentUser
     * @return int
     */
    public function getDefaultCampusFromUser($currentUser) {
        return 1;
    }

    // The Schoolbox connector doesn't return anything for getStaff().
}
