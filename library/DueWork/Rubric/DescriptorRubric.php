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

    public function addDescriptor($criterionID, DueWork_Rubric_Descriptor $descr) {
        $this->descriptors[$criterionID][$descr->getValue()] = $descr;
    }

    public function getDescriptor($criterionID, $descriptorIndex) {
        return $this->descriptors[$criterionID][$descriptorIndex];
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
