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
                return null;
            }

            // all types in the array must match
            $keys = array_keys($specification);
            for($i = 1; $i < $size; ++$i) {
                if(get_class($specification[$keys[$i]][0]) != get_class($specification[$keys[$i - 1]][0])) {
                    return null;
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
                return null;
            }
        }

        // insert results into rubric
        foreach($specification as $critID => $descs) {
            if($this->criteria[$critID]) {
                foreach($descs as $result) {
                    $rubric->addResult($critID, $result);
                }
            }
        }

        // prepare statement for retrieving criteria if it turns out to be required
        $stmt = Database::getInstance()->prepare("SELECT `id`, `grouping`, `name` FROM `rubric_criterion` WHERE `id` = :cid");

        // insert criteria into rubric
        foreach($this->criteria as $id => $checked) {
            // if the criterion in question has been retrieved and created, just insert it
            if($checked instanceof DueWork_Rubric_Criterion) {
                $rubric->addCriterion($checked);
            }
            // otherwise, use it as an ID, retrieve, create and insert
            elseif($checked) {
                $stmt->execute(array('cid' => $id));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $rubric->addCriterion(new DueWork_Rubric_Criterion($row['id'], $row['grouping'], $row['name']));
            }
        }
        return $rubric;
    }

}
