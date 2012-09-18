<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class DueWork_Rubric_DescriptorRubric extends DueWork_Rubric_Abstract {

    private $descriptors;   // Descriptor[]

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

    public function getResultIndices() {
        $keys = array_keys($this->descriptors);
        return array_keys($this->descriptors[$keys[0]]);
    }
}
