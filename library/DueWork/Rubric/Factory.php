<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class DueWork_Rubric_Factory {

    private $criteria;

    /**
     * @param array $criteria can either be an array of actual criteria, or just their IDs
     */
    public function __construct(array $criteria) {
        $this->criteria = $criteria;
    }

    /**
     * @param int|array $specification is an array of either results or descriptors
     * @param int $submissionBoxID
     * @return DueWork_Rubric_Abstract|null
     */
    public function buildRubric($specification, $submissionBoxID = 0) {

        if(is_numeric($specification)) {
            $rubric = new DueWork_Rubric_ResultSetRubric($submissionBoxID, new DueWork_Rubric_ResultSet($specification));
        }
        else {
            if(!($size = count($specification))) {
                throw new Exception("Zero Specification (resultset or criteria list is empty)");
            }

            // all types in the array must match
            $keys = array_keys($specification);
            for($i = 1; $i < $size; ++$i) {
                if(get_class($specification[$keys[$i]][0]) != get_class($specification[$keys[$i - 1]][0])) {
                    throw new Exception("Specification elements are inconsistent");
                }
            }

            // create a rubric based on the result type detected
            if($specification[$keys[0]][0] instanceof DueWork_Rubric_Result) {
                $rubric = new DueWork_Rubric_ResultSetRubric($submissionBoxID, new DueWork_Rubric_ResultSet());
            }
            elseif($specification[$keys[0]][0] instanceof DueWork_Rubric_Descriptor) {
                $rubric = new DueWork_Rubric_DescriptorRubric($submissionBoxID);
            }
            else {
                throw new Exception("Specification is of unknown type");
            }

	        // insert results into rubric
	        foreach($specification as $critID => $descs) {
	            if($this->criteria[$critID]) {
	                foreach($descs as $result) {
	                    $rubric->addResult($critID, $result);
	                }
	            }
	        }
        }

        $rubricHandler = new DueWork_Rubric_Handler();
        $this->criteria = $rubricHandler->populateCriteria($this->criteria);

        foreach($this->criteria as $criterion) {
            $rubric->addCriterion($criterion);
        }

        return $rubric;
    }

}
