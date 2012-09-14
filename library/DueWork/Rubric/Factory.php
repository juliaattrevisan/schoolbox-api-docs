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

    public function __construct(array $criteria) {
        $this->criteria = $criteria;
    }

    /**
     * @param int|array $specification can be either an int (resultset) or an array (descriptor)
     * @param int $submissionBoxID
     */
    public function buildRubric($specification, $submissionBoxID = 0) {
        if(is_numeric($specification)) {
            return $this->buildResultSetRubric($specification, $submissionBoxID);
        }
        elseif(is_array($specification)) {
            return $this->buildDescriptorRubric($specification, $submissionBoxID);
        }
        return null;
    }

    /**
     * Use the params to construct a descriptor rubric. This method is a little more involved than its counterpart.
     * @param array $criteria
     * @param array $descriptors
     * @param int $submissionBoxID
     * @return DueWork_Rubric_Abstract
     */
    public function buildDescriptorRubric(array $descriptors, $submissionBoxID = 0) {
        $rubric = new DueWork_Rubric_DescriptorRubric($submissionBoxID);

        foreach($descriptors as $critID => $descs) {
            if($this->criteria[$critID]) {
                foreach($descs as $value => $description) {
                    $rubric->addDescriptor($critID, new DueWork_Rubric_Descriptor($description, $value));
                }
            }
        }
        return $this->insertCriteria($rubric);
    }

    /**
     * Uses the params to construct a resultset rubric. Extra logic contained in the resultset constructor.
     * @param array $criteria
     * @param $resultSetID
     * @param int $submissionBoxID
     * @return DueWork_Rubric_Abstract
     */
    public function buildResultSetRubric($resultSetID, $submissionBoxID = 0) {
        $rubric = new DueWork_Rubric_ResultSetRubric($submissionBoxID, new DueWork_Rubric_ResultSet($resultSetID));
        return $this->insertCriteria($rubric);
    }

    /**
     * Delegate method to perform tasks common to both rubric types
     * @param DueWork_Rubric_Abstract $rubric
     * @param array $criteria
     * @return DueWork_Rubric_Abstract
     */
    private function insertCriteria(DueWork_Rubric_Abstract $rubric) {
        // get nominated criteria
        $stmt = Database::getInstance()->prepare("SELECT `id`, `grouping`, `name` FROM `rubric_criterion` WHERE `id` = :cid");

        foreach($this->criteria as $id => $checked) {
            if($checked instanceof DueWork_Rubric_Criterion) {
                $rubric->addCriterion($checked);
            }
            elseif($checked) {
                $stmt->execute(array('cid' => $id));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $rubric->addCriterion(new DueWork_Rubric_Criterion($row['id'], $row['grouping'], $row['name']));
            }
        }
        return $rubric;
    }
}
