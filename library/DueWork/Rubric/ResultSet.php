<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/**
 * Class DueWork_Rubric_ResultSet
 * @deprecated concept is entirely deprecated, no equivalent class exists
 */
class DueWork_Rubric_ResultSet {

    private $results; // Result[]

    /**
     * If specified, the resultSetID will be used to populate the resultset from the database
     * @deprecated
     *
     * @param int $resultSetID
     */
    public function __construct($resultSetID = 0) {
        if($resultSetID) {
            $handler = new DueWork_Rubric_Handler();
            $handler->populateResultSet($this, $resultSetID);
        }
    }

    public function addResult(DueWork_Rubric_Result $result) {
        $this->results[$result->getValue()] = $result;
    }

    public function getResults() {
        return $this->results;
    }

    public function getResult($value) {
        return $this->results[$value];
    }
}
