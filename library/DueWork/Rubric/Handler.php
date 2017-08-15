<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;

/**
 * Class DueWork_Rubric_Handler
 * @deprecated
 * @see RubricMapper
 */
class DueWork_Rubric_Handler
{
    /** @var Database */
    private $db;


    /**
     * DueWork_Rubric_Handler constructor.
     * @deprecated
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * This function should be renamed to something sensible
     * It retrieves a student's marks in a category, broken down by criteria and averaged
     *
     * @param $studentID
     * @param $categoryID
     *
     * @return array Indexed by criterion.id
     * @var string $return[rc.id]['name']     Criterion.name
     * @var int    $return[rc.id]['value']    Normalised mark (0 - 100)
     */
    public function getStudentMarksByCriteriaInCategory($studentID, $categoryID)
    {
        // Ordering:
        // box and criterion
        // most recent submissions first
        // highest marks first
        $stmt = Database::getInstance()->prepare(
            '
                SELECT
                    ru.submission_box_id AS box_id,
                    rd.criterion_id,
                    rd.value mark,
                    rc.name,
                    (
                        SELECT MAX(rdt.value)
                        FROM rubric_descriptor rdt
                        WHERE rd.criterion_id = rdt.criterion_id
                        AND sb.rubric_result_set = 0
                    ) AS dmax_mark,
                    (
                        SELECT MAX(rr.value)
                        FROM rubric_result rr
                        WHERE sb.rubric_result_set = rr.resultset_id
                        AND sb.rubric_result_set > 0
                    ) AS rmax_mark,
                    rm.created_at
                FROM rubric_mark rm
                JOIN rubric_descriptor rd ON rm.descriptor_id = rd.id
                JOIN rubric_criterion rc ON rd.criterion_id = rc.id
                JOIN rubric_usage ru ON rd.criterion_id = ru.criterion_id
                JOIN submission_box sb ON ru.submission_box_id = sb.id
                WHERE rm.valid = 1
                    AND rm.student_id = :student_id
                    AND sb.category = :category_id
                    AND sb.deleted IS FALSE
                    AND rm.published_at IS NOT NULL
                ORDER BY
                    ru.submission_box_id ASC,
                    rd.criterion_id ASC,
                    rm.created_at DESC,
                    rm.mark DESC
            '
        );
        $stmt->execute(
            array(
                'student_id'  => $studentID,
                'category_id' => $categoryID
            )
        );
        // create a normalised value for each criterion
        $crits = array();
        $names = array();

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $names[$row['criterion_id']] = $row['name'];

            // only use the most recent mark for each submission/criterion
            if (isset($crits[$row['criterion_id']][$row['box_id']])) {
                continue;
            }

            if ($row['dmax_mark']) {
                $localAvg = 0 == $row['dmax_mark'] ? null : $row['mark'] / $row['dmax_mark'];
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


    public function populateResultSet(DueWork_Rubric_ResultSet $resultSet, $resultSetID)
    {
        $stmt = $this->db->prepare("SELECT `value`, `label` FROM `rubric_result` WHERE `resultset_id` = :rsid");

        $stmt->execute(array('rsid' => $resultSetID));

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultSet->addResult(new DueWork_Rubric_Result($row['label'], $row['value']));
        }
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
            $sql = '
                SELECT c.grouping, c.id, c.name, COUNT(u.submission_box_id) usecount
                FROM rubric_criterion c
                LEFT JOIN rubric_usage u ON c.id = u.`criterion_id`
                WHERE c.parent_id = :pid
                AND c.parent_type = :ptype
                GROUP BY c.id
                ORDER BY `grouping` ASC, `name` ASC
            ';
        } else {
            $sql = '
                SELECT `grouping`, `id`, `name`
                FROM `rubric_criterion`
                WHERE `parent_id` = :pid
                AND `parent_type` = :ptype
                ORDER BY `grouping` ASC, `name` ASC
            ';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute(
            array(
                'pid'   => $parentID,
                'ptype' => $parentType
            )
        );

        $crits = array();
        /** @noinspection PhpAssignmentInConditionInspection */
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
}
