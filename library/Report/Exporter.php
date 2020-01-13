<?php
/**
 * Outputs a report in CSV format
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
        header('Content-type: text/csv; charset=UTF-8');
        header('Content-disposition: attachment; filename="report-' . $this->filename . '.csv"');

        $outstream = fopen("php://output", 'r');

        // output the column headings
        fputcsv($outstream, $this->getHeadings());

        // use array iteratively
        if (is_array($this->getData()) || $this->getData() instanceof Traversable) {
            foreach($this->getData() as $row) {
                $this->parseRow($outstream, $row);
            }
        }

        fclose($outstream);

        // don't keep going. stop now.
        exit(1);
    }

    /**
     * Check for and perform any data manipulation that needs doing, then write the row to file
     * @param $outstream
     * @param $row
     */
    protected function parseRow($outstream, $row) {
        if(is_callable($this->dataManipulatorCallback)) {
            $row = call_user_func_array($this->dataManipulatorCallback, array($row));
        }
        $datum = array();
        foreach($this->getHeadings() as $dbCol => $display) {
            $datum[] = $row[$dbCol];
        }

        fputcsv($outstream, $datum);
    }
}
