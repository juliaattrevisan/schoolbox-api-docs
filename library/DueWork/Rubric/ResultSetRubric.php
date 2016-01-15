<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/**
 * Class DueWork_Rubric_ResultSetRubric
 * @deprecated concept is entirely deprecated, no equivalent class exists
 */
class DueWork_Rubric_ResultSetRubric extends DueWork_Rubric_Abstract {

    protected $resultSet;     // ResultSet


    /**
     * DueWork_Rubric_ResultSetRubric constructor.
     * @deprecated
     *
     * @param                          $submissionBoxID
     * @param DueWork_Rubric_ResultSet $resultSet
     */
    public function __construct($submissionBoxID, DueWork_Rubric_ResultSet $resultSet) {
        $this->submissionBoxID = $submissionBoxID;
        $this->resultSet = $resultSet;
    }

    public function getResultCount() {
        return count($this->resultSet->getResults());
    }

    public function getResultIndices($criterion) {
        return array_keys($this->resultSet->getResults());
    }

    /**
     * @param $criterionID (disregarded)
     * @param $value
     * @return mixed
     */
    public function getResult($criterionID, $value) {
        return $this->resultSet->getResult($value);
    }

    /**
     * @param $criterionID (disregarded)
     * @param DueWork_Rubric_Result $resultObject
     */
    public function addResult($criterionID, $resultObject) {
        $this->resultSet->addResult($resultObject);
    }

    public function getResults($criterionID = 0) {
        return $this->resultSet->getResults();
    }
}
