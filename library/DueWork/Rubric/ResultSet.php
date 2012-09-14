<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class DueWork_Rubric_ResultSet {

    private $results; // Result[]

    /**
     * If specified, the resultSetID will be used to populate the resultset from the database
     * @param int $resultSetID
     */
    public function __construct($resultSetID = 0) {
        if($resultSetID) {
            $stmt = Database::getInstance()->prepare("SELECT `value`, `label` FROM `rubric_result` WHERE `resultset_id` = :rsid");

            $stmt->execute(array('rsid' => $resultSetID));

            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->addResult(new DueWork_Rubric_Result($row['label'], $row['value']));
            }
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
