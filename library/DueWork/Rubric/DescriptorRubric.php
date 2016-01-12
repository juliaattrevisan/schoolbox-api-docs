<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/**
 * Class DueWork_Rubric_DescriptorRubric
 * @deprecated use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper
 */
class DueWork_Rubric_DescriptorRubric extends DueWork_Rubric_Abstract {

    private $descriptors;   // Descriptor[]


    /**
     * DueWork_Rubric_DescriptorRubric constructor.
     * @deprecated
     *
     * @param $submissionBoxID
     */
    public function __construct($submissionBoxID) {
        parent::__construct($submissionBoxID, 0);
    }

    /**
     * @param $criterionID
     * @param DueWork_Rubric_Descriptor $descriptor
     */
    public function addResult($criterionID, $descriptor) {
        $this->descriptors[$criterionID][$descriptor->getValue()] = $descriptor;
    }

    /**
     * @param $criterionID
     * @param $value
     * @return DueWork_Rubric_Descriptor
     */
    public function getResult($criterionID, $value) {
        if(isset($this->descriptors[$criterionID][$value])) {
            return $this->descriptors[$criterionID][$value];
        }
        return null;
    }

    public function getResultCount() {
        $keys = array_keys($this->descriptors);
        return count($this->descriptors[$keys[0]]);
    }

    public function getResultIndices($criterion) {
        $keys = array_keys($this->descriptors);
        return array_keys($this->descriptors[$criterion]);
    }

    public function getResults($criterionID = 0) {
        if($criterionID) {
            if(isset($this->descriptors[$criterionID])) {
                return $this->descriptors[$criterionID];
            }
            return array();
        }
        else {
            return $this->descriptors;
        }
    }
}
