<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/**
 * Class DueWork_Rubric_Mark
 * @deprecated use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Mark
 */
class DueWork_Rubric_Mark {

    private $rubric;
    private $markArray;


    /**
     * DueWork_Rubric_Mark constructor.
     * @deprecated
     *
     * @param DueWork_Rubric_Abstract $rubric
     */
    public function __construct(DueWork_Rubric_Abstract $rubric) {
        $this->rubric = $rubric;

        // set up markset
        $criteria = $rubric->getCriteriaIndices();

        if(count($criteria)) {
            foreach($criteria as $x) {
                $this->unsetMark($x);
            }
        }
    }

    public function setMark($criterionID, $value) {
        if($value >= 0 && $this->isValidCriterionID($criterionID)) {
            $this->markArray[$criterionID] = $value;
        }
    }

    /**
     * Populate self with student marks, based on a timestamp set
     * @param $studentID
     * @param int $set
     * @return bool
     */
    public function populateMarks($studentID, $set = 0) {
        $rubricHandler = new DueWork_Rubric_Handler();
        $marks = $rubricHandler->getStudentMarks($studentID, $this->rubric->getSubmissionBoxID(), $set);
        if(count($marks)) {
            foreach($marks as $cID => $mark) {
                $this->setMark($cID, $mark);
            }
            return true;
        }
        return false;
    }


    public function unsetMark($criterionID) {
        if($this->isValidCriterionID($criterionID)) {
            $this->markArray[$criterionID] = -1;
        }
    }

    public function getMark($criterionID) {
        if(isset($this->markArray[$criterionID])) {
            return $this->markArray[$criterionID];
        }
        return false;
    }

    protected function isValidCriterionID($id) {
        return in_array($id, $this->rubric->getCriteriaIndices());
    }

    public function toArray() {
        $obj = array();

        foreach($this->rubric->getCriteriaIndices() as $cID) {
            $obj['criteria'][$cID] = array(
                'id'    => $cID,
                'group' => $this->rubric->getCriterion($cID)->getGrouping(),
                'label' => $this->rubric->getCriterion($cID)->getName(),
                'value' => $this->getMark($cID)
            );

            // data replication for the sake of consistency
            if($this->rubric instanceof DueWork_Rubric_ResultSetRubric) {
                foreach($this->rubric->getResultIndices(null) as $i) {
                    $obj['criteria'][$cID]['results'][$i] = $this->rubric->getResult($cID, $i)->getLabel();
                }
            }
        }

        if($this->rubric instanceof DueWork_Rubric_DescriptorRubric) {
            foreach($this->rubric->getCriteriaIndices() as $i) {
                foreach($this->rubric->getResultIndices($i) as $j) {
                    $obj['criteria'][$i]['results'][$j] = $this->rubric->getResult($i, $j)->getDescription();
                }
            }
        }

        // this array must be sequential!
        $obj = array_values($obj['criteria']);
        return array('criteria' => $obj);
    }
}
