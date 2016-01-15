<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/**
 * Class DueWork_Rubric_Abstract
 *
 * @deprecated use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper
 */
abstract class DueWork_Rubric_Abstract {

    protected $submissionBoxID; // int
    protected $criteria;        // Criterion[]


    /**
     * DueWork_Rubric_Abstract constructor.
     * @deprecated
     *
     * @param $submissionBoxID
     */
    public function __construct($submissionBoxID) {
        $this->submissionBoxID = $submissionBoxID;
    }

    public function addCriterion(DueWork_Rubric_Criterion $crit) {
        $this->criteria[$crit->getID()] = $crit;
    }

    public function getCriteriaCount() {
        return count($this->criteria);
    }

    public function getCriteriaIndices() {
        return array_keys($this->criteria);
    }

    /**
     * @param $criterionID
     * @return DueWork_Rubric_Criterion
     */
    public function getCriterion($criterionID) {
        if(isset($this->criteria[$criterionID])) {
            return $this->criteria[$criterionID];
        }
        return null;
    }

    public function getSubmissionBoxID() {
        return $this->submissionBoxID;
    }

    public abstract function getResultCount();

    public abstract function getResultIndices($criterion);

    /**
     * @abstract
     * @param $criterionID
     * @param $resultObject
     */
    public abstract function addResult($criterionID, $resultObject);

    /**
     * @abstract
     * @param $criterionID
     * @param $value
     * @return DueWork_Rubric_Descriptor|DueWork_Rubric_Result
     */
    public abstract function getResult($criterionID, $value);

    /**
     * @abstract
     * @param $criterionID
     * @return mixed
     */
    public abstract function getResults($criterionID = 0);
}
