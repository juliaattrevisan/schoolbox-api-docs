<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class DueWork_Rubric_Handler
{

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }


    /**
     * @param $boxID
     *
     * @return DueWork_Rubric_Abstract
     */
    public function getRubric($boxID)
    {

        // setup
        $criteria     = array();
        $results      = array();
        $resultCounts = array();

        // <SQL>
        $sql = "
            SELECT
                c.id c_id, c.grouping c_grouping, c.name c_name,
                sb.`rubric_result_set` sb_resultset,
                r.value r_value, r.label r_label,
                d.value d_value, d.`description` d_description
            FROM rubric_usage u
                LEFT JOIN `submission_box` sb
                    ON u.`submission_box_id` = sb.id
                LEFT JOIN rubric_criterion c
                    ON u.`criterion_id` = c.id
                LEFT JOIN rubric_result r
                    ON r.`resultset_id` = sb.`rubric_result_set`
                    AND sb.`rubric_result_set` > 0
                LEFT JOIN rubric_descriptor d
                    ON d.`criterion_id` = u.`criterion_id`
                    AND d.`submission_box_id` = u.`submission_box_id`
                    AND sb.`rubric_result_set` = 0
            WHERE u.submission_box_id = :sbid
            AND (d.`valid` = 1 OR sb.rubric_result_set > 0)
            ORDER BY c.grouping ASC, c.name ASC, r.value ASC, d.value ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array('sbid' => $boxID));
        // </SQL>


        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // if criterion doesn't yet exist, create it
            if (!isset($criteria[$row['c_id']])) {
                $criteria[$row['c_id']] = new DueWork_Rubric_Criterion(
                    $row['c_id'],
                    $row['c_grouping'],
                    $row['c_name']
                );
            }

            ++$resultCounts[$row['c_id']];

            if ($row['sb_resultset']) {
                // use resultset
                $results[$row['c_id']][$row['r_value']] = new DueWork_Rubric_Result(
                    $row['r_label'],
                    $row['r_value']
                );
            } else {
                // use descriptors
                $results[$row['c_id']][$row['d_value']] = new DueWork_Rubric_Descriptor(
                    $row['d_description'],
                    $row['d_value']
                );
            }
        }

        // the 2nd dimension of $results must all be the same size: get the min size and truncate if required
        $size = count($resultCounts) ? min($resultCounts) : 0;

        foreach ($results as $cID => $foo) {
            $results[$cID] = array_slice($results[$cID], 0, $size);
        }

        // create factory
        $rubricFactory = new DueWork_Rubric_Factory($criteria);

        return $rubricFactory->buildRubric($results, $boxID);

    }

    /**
     * Gets an array representing the marks given by the rubric marking in question
     *
     * @param     $studentID
     * @param     $boxID
     * @param int $set
     *
     * @return array
     */
    public function getStudentMarks($studentID, $boxID, $set = 0)
    {
        $stmt = Database::getInstance()->prepare(
            "
                        SELECT criterion_id, mark, UNIX_TIMESTAMP(tstamp) AS tstamp
                        FROM rubric_mark
                        WHERE student_id = :sid
                        AND submission_box_id = :sbid
                        AND valid = 1
                        ORDER BY tstamp DESC, criterion_id ASC
                    "
        );

        $stmt->execute(
            array(
                'sid'  => $studentID,
                'sbid' => $boxID
            )
        );

        $marks = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $marks[$row['tstamp']][$row['criterion_id']] = $row['mark'];
        }

        // remove timetstamps and replace with indices
        $marks = array_values($marks);
        if (isset($marks[$set])) {
            return $marks[$set];
        }
        return array();
    }

    /**
     * Gets an array representing the marks given by the rubric marking in question
     * Includes description
     *
     * @param     $studentID
     * @param     $boxID
     * @param int $set
     *
     * @return array
     */
    public function getStudentMarksWithDescription($studentID, $boxID, $set = 0)
    {
        // Search both rubric_descriptor and rubric_result for description
        $stmt = Database::getInstance()->prepare("
            SELECT
                rm.criterion_id,
                rm.mark,
                COALESCE(rd.description, rr.label) AS description,
                UNIX_TIMESTAMP(rm.tstamp) AS tstamp
            FROM rubric_mark rm
            LEFT JOIN rubric_descriptor rd ON rm.criterion_id = rd.criterion_id AND rm.mark = rd.value
            LEFT JOIN submission_box sb ON rm.submission_box_id = sb.id
            LEFT JOIN rubric_result rr ON sb.rubric_result_set = rr.resultset_id AND rm.mark = rr.value
            WHERE rm.student_id = :sid
            AND rm.submission_box_id = :sbid
            AND rm.valid = 1
            ORDER BY rm.tstamp DESC, rm.criterion_id ASC
        ");

        $stmt->execute(
            array(
                'sid'  => $studentID,
                'sbid' => $boxID
            )
        );

        $marks = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $marks[$row['tstamp']][$row['criterion_id']]['mark'] = $row['mark'];
            $marks[$row['tstamp']][$row['criterion_id']]['description'] = $row['description'];
        }

        // Use the youngest rubric mark set.
        if (count($marks)) {
            ksort($marks);
            $marks = array_pop(array_values($marks));
        }

        return $marks;
    }

    /**
     * This function should be renamed to something sensible
     * It retrieves a student's marks in a category, broken down by criteria and averaged
     *
     * @param $studentID
     * @param $categoryID
     *
     * @return array
     */
    public function getStudentMarksByCriteriaInCategory($studentID, $categoryID)
    {
        // Ordering:
        // box and criterion
        // most recent submissions first
        // highest marks first
        $stmt = Database::getInstance()->prepare(
            "
                SELECT
                    sb.id AS box_id,
                    rm.criterion_id,
                    rm.mark,
                    rc.name,
                    (
                        SELECT MAX(rd.value)
                        FROM rubric_descriptor rd
                        WHERE rm.criterion_id = rd.criterion_id
                        AND sb.rubric_result_set = 0
                    ) AS dmax_mark,
                    (
                        SELECT MAX(rr.value)
                        FROM rubric_result rr
                        WHERE sb.rubric_result_set = rr.resultset_id
                        AND sb.rubric_result_set > 0
                    ) AS rmax_mark,
                    rm.tstamp
                FROM rubric_mark rm
                LEFT JOIN rubric_criterion rc
                    ON rm.criterion_id = rc.id
                LEFT JOIN
                    submission_box sb
                    ON rm.submission_box_id = sb.id
                WHERE rm.valid = 1
                    AND sb.mark_type = :mark_type
                    AND rm.student_id = :student_id
                    AND sb.category = :category_id
                    AND sb.deleted IS FALSE
                ORDER BY
                    sb.id ASC,
                    rm.criterion_id ASC,
                    rm.tstamp DESC,
                    rm.mark DESC
            "
        );
        $stmt->execute(
            array(
                'mark_type'   => \Schoolbox\Assessment\Component\SubmissionBox\MarkType::TYPE_RUBRIC,
                'student_id'  => $studentID,
                'category_id' => $categoryID
            )
        );
        // create a normalised value for each criterion
        $crits = array();
        $names = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $names[$row['criterion_id']] = $row['name'];

            // only use the most recent mark for each submission/criterion
            if (isset($crits[$row['criterion_id']][$row['box_id']])) {
                continue;
            }

            if ($row['dmax_mark']) {
                $localAvg = $row['mark'] / $row['dmax_mark'];
            } elseif ($row['rmax_mark']) {
                $localAvg = $row['mark'] / $row['rmax_mark'];
            } else {
                continue;
            }

            $crits[$row['criterion_id']][$row['box_id']] = $localAvg;
        }

        $return = array();

        foreach ($crits as $critID => $marks) {
            $return[$critID]['name'] = $names[$critID];
            $return[$critID]['value'] = (array_sum($marks) / count($marks)) * 100;
        }

        return $return;
    }

    /**
     * Gets the total mark for the rubric marking in question
     *
     * @param     $studentID
     * @param     $boxID
     * @param int $set
     *
     * @return array
     */
    public function getStudentMarksTotal($studentID, $boxID, $set = 0)
    {

        // test for a rubric mark
        $marks = $this->getStudentMarks($studentID, $boxID, $set);

        if (count($marks)) {
            $result = array(
                'mark' => array_sum($marks),
                'max'  => $this->getRubricMaxScore($boxID)
            );
        } else {
            $result = array(
                'mark' => -1,
                'max'  => -1
            );
        }

        // make sure there's not an error code on the submissionreturn table
        $dem  = DueWork_ErrorMapper::getInstance();
        $errs = $dem->listDisplayValues();

        $returnHandler = new SubmissionReturn();
        $rtn           = $returnHandler->getOneByUser($studentID, $boxID);
        if (in_array($rtn['mark'], $errs)) {
            $result['err'] = $rtn['mark'];
        }

        return $result;
    }

    public function populateResultSet(DueWork_Rubric_ResultSet $resultSet, $resultSetID)
    {
        $stmt = $this->db->prepare("SELECT `value`, `label` FROM `rubric_result` WHERE `resultset_id` = :rsid");

        $stmt->execute(array('rsid' => $resultSetID));

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultSet->addResult(new DueWork_Rubric_Result($row['label'], $row['value']));
        }
    }

    public function populateCriteria($criterionList)
    {

        $returnList = array();

        // prepare statement for retrieving criteria if it turns out to be required
        $stmt = Database::getInstance()->prepare(
            "SELECT `id`, `grouping`, `name` FROM `rubric_criterion` WHERE `id` = :cid"
        );

        // insert criteria into rubric
        foreach ($criterionList as $id => $checked) {
            if ($checked instanceof DueWork_Rubric_Criterion) {
                $returnList[$id] = $checked;
            } // if the criterion is just an index, retrieve it from the db and replace
            elseif ($checked && is_numeric($checked)) {
                $stmt->execute(array('cid' => $id));
                $row             = $stmt->fetch(PDO::FETCH_ASSOC);
                $returnList[$id] = new DueWork_Rubric_Criterion($row['id'], $row['grouping'], $row['name']);
            }
        }
        return $returnList;
    }

    /**
     * @param $boxID
     *
     * @return bool
     */
    public function marksAlreadyGiven($boxID)
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT student_id) FROM `rubric_mark` WHERE `submission_box_id` = :sbid"
        );
        $stmt->execute(array('sbid' => $boxID));
        return $stmt->fetchColumn() ? true : false;
    }

    /**
     * @param        $parentID
     * @param string $parentType
     *
     * @return array
     */
    public function getAvailableCriteria($parentID, $parentType = 'category', $withUsageCount = false)
    {
        if ($withUsageCount) {
            $sql = "
                SELECT c.grouping, c.id, c.name, COUNT(u.submission_box_id) usecount
                FROM rubric_criterion c
                LEFT JOIN rubric_usage u ON c.id = u.`criterion_id`
                WHERE c.parent_id = :pid
                AND c.parent_type = :ptype
                GROUP BY c.id
                ORDER BY `grouping` ASC, `name` ASC
            ";
        } else {
            $sql = "
                SELECT `grouping`, `id`, `name`
                FROM `rubric_criterion`
                WHERE `parent_id` = :pid
                AND `parent_type` = :ptype
                ORDER BY `grouping` ASC, `name` ASC
            ";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute(
            array(
                'pid'   => $parentID,
                'ptype' => $parentType
            )
        );

        $crits = array();
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $val = array(
                'id'   => $row[1],
                'name' => $row[2]
            );
            if ($withUsageCount) {
                $val['count'] = $row[3];
            }
            $crits[$row[0]][$row[1]] = $val;
        }
        return $crits;
    }

    /**
     * @return array
     */
    public function getResultSets()
    {
        $sql = "
            SELECT `resultset_id`, `value`, `label`
            FROM `rubric_result`
            ORDER BY `resultset_id` ASC, `value` ASC
        ";

        $rs = array();

        $result = $this->db->query($sql);
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $rs[$row[0]][$row[1]] = $row[2];
        }

        return $rs;
    }

    public function saveMarks($marks, $studentID, $boxID, $stamp = 0)
    {
        if ($stamp) {
            return $this->updateMarks($marks, $studentID, $boxID, $stamp);
        } else {
            return $this->createMarks($marks, $studentID, $boxID);
        }
    }

    public function createMarks($marks, $studentID, $boxID)
    {
        $stmt = $this->db->prepare(
            "
                INSERT INTO rubric_mark (`student_id`,`submission_box_id`, `criterion_id`, `mark`)
                VALUES (:sid, :sbid, :cid, :mark)
                ON DUPLICATE KEY UPDATE mark = :mark
            "
        );

        foreach ($marks as $cID => $mark) {
            $stmt->execute(
                array(
                    'sid'  => $studentID,
                    'sbid' => $boxID,
                    'cid'  => $cID,
                    'mark' => $mark
                )
            );
        }
        return true;
    }

    public function updateMarks($marks, $studentID, $boxID, $stamp)
    {

        // the ON DUPLICATE clause is equivalent to the nonexistent ON DUPLICATE KEY IGNORE
        $insertStmt = $this->db->prepare(
            "
                INSERT INTO rubric_mark (`student_id`,`submission_box_id`, `criterion_id`, `tstamp`, `mark`)
                VALUES (:sid, :sbid, :cid, :stamp, :mark)
                ON DUPLICATE KEY UPDATE valid = valid
            "
        );

        $updateStmt = $this->db->prepare(
            "
                UPDATE rubric_mark
                SET `mark` = :mark
                WHERE student_id = :sid
                AND submission_box_id = :sbid
                AND criterion_id = :cid
                AND tstamp = :stamp
                AND valid = 1
            "
        );

        foreach ($marks as $cID => $mark) {
            // attempt an update of a record
            $updateStmt->execute(
                array(
                    'mark'  => $mark,
                    'sid'   => $studentID,
                    'sbid'  => $boxID,
                    'cid'   => $cID,
                    'stamp' => $stamp
                )
            );

            // if nothing happened, we need to insert a record that matches the timestamp in question
            if (!$updateStmt->rowCount()) {
                $insertStmt->execute(
                    array(
                        'sid'   => $studentID,
                        'sbid'  => $boxID,
                        'cid'   => $cID,
                        'stamp' => $stamp,
                        'mark'  => $mark
                    )
                );
            }
        }
        return true;
    }

//	/**
//	 * Bin this
//	 * @deprecated
//	 * @param $studentID
//	 * @param $boxID
//	 * @return string
//	 */
//	public function getLatestMarkTimestamp($studentID, $boxID) {
//        $db = Database::getInstance();
//        $stmt = $db->prepare("
//            SELECT MAX(tstamp) FROM rubric_mark
//            WHERE student_id = :sid
//            AND submission_box_id = :sbid
//            AND valid = 1
//        ");
//
//        $stmt->execute(array(
//            'sid'  => $studentID,
//            'sbid' => $boxID,
//        ));
//
//        return $stmt->fetchColumn();
//    }

    /**
     * @param $rubric                  DueWork_Rubric_Abstract
     * @param $invalidateExistingMarks bool
     * @param $submissionBoxID         int
     */
    public function persistRubric($rubric, $invalidateExistingMarks, $submissionBoxID)
    {

        // 0) invalidate existing marks if required
        if (isset($invalidateExistingMarks) && $invalidateExistingMarks === true) {
            $stmt = $this->db->prepare(
                "
                                UPDATE rubric_mark
                                SET valid = 0
                                WHERE submission_box_id = :sbid
                            "
            );
            $stmt->execute(array('sbid' => $submissionBoxID));
        }

        // 1) remove existing rubric_usage entries for this submissionbox for data integrity
        $stmt = $this->db->prepare(
            "
                        DELETE FROM rubric_usage
                        WHERE submission_box_id = :sbid
                    "
        );
        $stmt->execute(array('sbid' => $submissionBoxID));

        // 2) add the rubric usages for this submission box
        $stmt = $this->db->prepare(
            "
                        INSERT INTO rubric_usage
                        VALUES (:cid, :sbid)
                    "
        );
        if ($rubric->getCriteriaCount()) {
            foreach ($rubric->getCriteriaIndices() as $i) {
                $stmt->execute(
                    array(
                        'cid'  => $i,
                        'sbid' => $submissionBoxID
                    )
                );
            }
        }

        // 3) add the descriptors into into the database, if necessary
        // the ResultSetRubric doesn't need any additional data stored
        if ($rubric instanceof DueWork_Rubric_DescriptorRubric) {
            // invalidate any existing descriptors for this submission box,
            // and we will revalidate the ones we plan to use
            $stmt = $this->db->prepare(
                "
                    UPDATE rubric_descriptor
                    SET valid = 0
                    WHERE submission_box_id = :sbid
                "
            );
            $stmt->execute(array('sbid' => $submissionBoxID));

            // insert nonexistent descriptors,
            // or replace existing ones with their new descriptions if they already exist
            $stmt = $this->db->prepare(
                "
                    INSERT INTO rubric_descriptor (`criterion_id`,`submission_box_id`,`value`,`description`)
                    VALUES (:cid, :sbid, :val, :descr)
                    ON DUPLICATE KEY UPDATE `description` = :descr, `valid` = 1
                "
            );

            if ($rubric->getCriteriaCount() && $rubric->getResultCount()) {
                foreach ($rubric->getCriteriaIndices() as $i) {
                    foreach ($rubric->getResultIndices($i) as $j) {
                        $subst = array(
                            'cid'   => $i,
                            'sbid'  => $submissionBoxID,
                            'val'   => $j,
                            'descr' => $rubric->getResult($i, $j)->getDescription()
                        );
                        $stmt->execute($subst);
                    }
                }
            }
        }

        // 4) if marks have been given, the most recent needs to be updated on the submission_return table
        // note that the display mark isn't being altered, just the normalised one

        // determine the scalefactor based on how the rubric has changed
        $max = $this->getRubricMaxScore($submissionBoxID);

        // get a list of students to update
        $stmt = $this->db->prepare(
            "SELECT DISTINCT `owner` FROM submission_return WHERE box_id = :sbid"
        );
        $stmt->execute(array('sbid' => $submissionBoxID));
        $studentList = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($studentList)) {
            // prepare statement to retrieve updated marks
            $stmt = $this->db->prepare(
                "
                    UPDATE submission_return
                    SET norm_mark = (
                        SELECT SUM(mark) AS newmark FROM (
                            SELECT * FROM (
                                SELECT *
                                FROM rubric_mark
                                WHERE submission_box_id = :sbid
                                AND student_id = :sid
                                AND valid = 1
                                ORDER BY tstamp DESC
                            ) t
                            GROUP BY criterion_id
                        ) u
                    ) / :max * 100
                    WHERE box_id = :sbid
                    AND `owner` = :sid
                    ORDER BY returned DESC
                    LIMIT 1
                "
            );

            foreach ($studentList as $row) {
                $stmt->execute(
                    array(
                        'sbid' => $submissionBoxID,
                        'sid'  => $row['owner'],
                        'max'  => $max
                    )
                );
            }
        }
    }


    public function getRubricMaxScore($submissionBoxID)
    {

        $max = 0;

        /* if descriptor */
        $stmt = $this->db->prepare(
            "SELECT MAX(d.value)
                        FROM submission_box sb
                        LEFT JOIN `rubric_usage` u ON sb.id = u.`submission_box_id`
                        LEFT JOIN rubric_criterion c ON c.id = u.`criterion_id`
                        LEFT JOIN rubric_descriptor d ON d.`submission_box_id` = sb.id
                            AND d.`criterion_id` = c.id AND sb.`rubric_result_set` = 0
                        WHERE d.valid = 1 AND sb.id = :sbid
                        GROUP BY (c.id)"
        );
        $stmt->execute(array('sbid' => $submissionBoxID));
        foreach ($stmt as $result) {
            $max += $result[0];
        }

        if ($max > 0) {
            return $max;
        } else {
            /* attempt result set max */
            $stmt = $this->db->prepare(
                "
                    SELECT r.value r_value
                    FROM submission_box sb
                    LEFT JOIN `rubric_usage` u ON sb.id = u.`submission_box_id`
                    LEFT JOIN rubric_result r ON r.`resultset_id` = sb.`rubric_result_set`
                        AND sb.`rubric_result_set` > 0
                    WHERE sb.rubric_result_set > 0 AND r.value > 0 AND sb.id = :sbid
                    ORDER BY r.value DESC
                "
            );

            $stmt->execute(array('sbid' => $submissionBoxID));
            return $stmt->rowCount();
        }
    }
}
