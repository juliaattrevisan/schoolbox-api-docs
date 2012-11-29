<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 *
 * nB. Cross-strategy dependency: this abstraction is based on the Synergetic version and has little separate
 *     functionality of its own
 */
abstract class Synchronise_External_Strategy_Abstract extends Synchronise_Synergetic_Strategy_Abstract {

    // object to handle the verbose (or not) output of the sync script
    protected $verboseFilter = null;

    // private access to prevent implementations from modifying the columns mid-flight
    private $columnList = array();

    protected $entityData;

    protected $definition;

    protected $fp;

    public function __construct($csvFile, $columns) {
        parent::__construct();
        ini_set('auto_detect_line_endings', 1);

        if(!@is_readable($csvFile)) {
            throw new UnderflowException("Input file '$csvFile' is not readable.");
        }
        $this->fp = fopen($csvFile, 'r');
        $this->setColumns($columns);
    }

    /**
     * Set the columns of the strategy about to be implemented
     * @param array $columns
     */
    protected function setColumns(array $columns) {
        $this->columnList = $columns;
    }


    /**
     * Parse one line of the CSV referenced by the file pointer and return a mapping to the column list set
     *
     * @param $fp
     * @param null $delim
     * @param null $encl
     * @param null $esc
     * @return array
     * @throws LengthException
     */
    protected function parseCsvRow(&$fp) {
        // retrieve next line
        $line = fgetcsv($fp);

        // check that we haven't reached EOF
        if($line) {
            // assign mapping
            if(count($line) != count($this->columnList)) {
                $exp = count($this->columnList);
                $got = count($line);
                throw new LengthException("Unexpected number of fields in row: expected $exp, got $got.");
            }
            return array_combine($this->columnList, $line);
        }
        return false;
    }


    /**
     * Get a list of records to process.
     *
     * @return void
     */
    public function generateList() {
        while($entity = $this->parseCsvRow($this->fp)) {
            $this->entityData[] = $entity;
        }
    }


    /**
     * Process them
     *
     */
    public abstract function createIfExists();

}
