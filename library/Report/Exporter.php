<?php
/**
 * Outputs a report in CSV format
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Report_Exporter {

    private $reportHeadings = array();
    private $reportData = array();
    private $filename = null;
    private $dataManipulatorCallback;

    /**
     * @param Report_Abstract $report
     * @param $filename
     */
    public function __construct($filename) {
        $this->filename = $filename;
    }

    public function setHeadings($headings) {
        $this->reportHeadings = $headings;
    }

    public function setData($data, $callback = null) {
        $this->reportData = $data;
        $this->dataManipulatorCallback = $callback;
    }

    protected function getHeadings() {
        return $this->reportHeadings;
    }

    protected function getData() {
        return $this->reportData;
    }

    /**
     * Convenience function for output format
     * @param $format
     */
    public function export($format) {
        switch(strtolower($format)) {
            // output a csv file
            case 'csv':
                $this->exportCSV();
                break;

            // output in JSON
            case 'json':
                // $this->exportJSON();
                // break;
            // otherwise, do nothing
            default:
                break;
        }
    }

    /**
     * @output csv download object
     */
    public function exportCSV() {
        // standardise and remove unhealthy characters (ie. spaces) from the name
        $this->filename = str_replace(' ', '_', strtolower($this->filename));

        // set the output headers
        header('Content-type: text/csv');
        header('Content-disposition: attachment; filename="report-' . $this->filename . '.csv"');

        // output the column headings
        $this->__outputCSV(array($this->getHeadings()));

        // format data for output - filter and order by column headers specified
        $data = array();

        // use resource iteratively
        if(is_resource($this->getData())) {
            $db = DeprecatedDatabase::getInstance();
            while($row = $db->getNextArray($this->getData())) {
                $data[] = $this->parseRow($row);
            }
        }

        // use array iteratively
        elseif(is_array($this->getData())) {
            foreach($this->getData() as $row) {
                $data[] = $this->parseRow($row);
            }
        }

        // output data
        $this->__outputCSV($data);

        // don't keep going. stop now.
        exit(1);
    }

    /**
     * Check for and perform any data manipulation that needs doing
     * @param $row
     * @return array
     */
    protected function parseRow($row) {
        if(is_callable($this->dataManipulatorCallback)) {
            $row = call_user_func_array($this->dataManipulatorCallback, array($row));
        }
        $datum = array();
        foreach($this->getHeadings() as $dbCol => $display) {
            $datum[] = $row[$dbCol];
        }
        return $datum;
    }

    /**
     * Output a csv row, using the __outputCSV method
     *
     * @param  $data
     * @return void
     */
    protected function __outputCSV($data) {
        $outstream = fopen("php://output", 'r');
        array_walk($data, array($this, '__outputCSVRow'), $outstream);
        fclose($outstream);
    }

    /**
     * Output a csv row.
     *
     * @param  $vals
     * @param  $key
     * @param  $filehandler
     * @return void
     */
    protected function __outputCSVRow(&$vals, $key, $filehandler) {
        fputcsv($filehandler, $vals, ',', '"');
    }

}
