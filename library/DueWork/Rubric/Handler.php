<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Criterion;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\Term;

/**
 * Class DueWork_Rubric_Handler
 *
 * @deprecated
 * @see RubricMapper
 */
class DueWork_Rubric_Handler
{

    /** @var Database */
    private $db;


    /**
     * DueWork_Rubric_Handler constructor.
     *
     * @deprecated
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }


    /**
     * Returns normalised marks for all student rubric marks in specified folders
     * on assessments due in the specified term.
     *
     * Does not respect assessment.weight, ie all assessments are weighted at 1.
     *
     * If a student mark has a NULL value, it is ignored for aggregation.
     *
     * Normalised mark: (0.0 - 100.0)
     *
     * @param int[]      $folderIds
     * @param Term\Model $term
     * @return array
     * @var string       $data [sb.id][rc.id]['grouping'] criterion.grouping
     * @var string       $data [sb.id][rc.id]['name'] criterion.name
     * @var string       $data [sb.id][rc.id]['max_mark'] max mark
     * @var array        $data [sb.id][rc.id]['marks']
     * @var float        $data [sb.id][rc.id]['marks'][student.id]
     */
    public function getStudentMarksByCriteriaInFolder(array $folderIds, Term\Model $term)
    {
        if (!count($folderIds)) {
            return [];
        }

        /* 1. Get latest student published marks per criterion and dueWork
         * 2. Find the max descriptor value per criterion and dueWork.
         * 3. Convert each student mark into a normalised value
         *
         * We could perform steps 1+2 in a single query using a correlated subquery, but
         * that would mean the subquery is executed for every row fetched by the outer query.
         * In our case it is much faster to just fetch the max descriptors in a subsequent light-weight query.
         * (eg, consider there is no point executing the subquery for different students)
         * @see http://www.sqlservice.se/sql-server-performance-death-by-correlated-subqueries/
         */
        $pb = new Alaress\Database\Predicate\Builder($this->db, '');
        $pb->equals('rc.parent_id', $folderIds)
           ->equals('rc.parent_type', Criterion::PARENT_TYPE_CATEGORY)
           ->greaterThanOrEqualTo('a.due_date', $term->getStart()->getDatabase());

        $finish = $term->getActualFinish();
        if (null !== $finish && !$finish->isNull()) {
            $pb->lessThan('a.due_date', $finish->getDatabase());
        }

        $whereClauseInner = implode(' AND ', $pb->getWhere());
        $whereClauseOuter = implode(' AND ', $pb->getWhere());

        // 1. Get latest published student marks
        $stmt = Database::getInstance()->prepare("
            SELECT sr.owner, sb.id AS 'sb.id', rc.id AS 'rc.id', rd.value AS 'mark'
            FROM rubric_mark rm
            JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id AND rd.deleted_at IS NULL
            JOIN rubric_criterion rc  ON rc.id = rd.criterion_id AND rc.deleted_at IS NULL
            JOIN submission_return sr ON sr.id = rm.return_id
            JOIN submission_box sb    ON sb.id = sr.box_id
            JOIN assessment a         ON  a.id = sb.assessment_id
            JOIN (
                -- ^Changed from LEFT JOIN to JOIN to allow filter out deleted feedbacks 
                -- Get the latest published marks
                SELECT
                    MAX(sr.published_at) AS 'max_published_at',
                    sr.id AS r_id,
                    sr.owner AS 'student_id',
                    sr.box_id AS 'sb_id'
                FROM rubric_mark rm
                JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id AND rd.deleted_at IS NULL
                JOIN rubric_criterion rc  ON rc.id = rd.criterion_id AND rc.deleted_at IS NULL
                JOIN submission_return sr ON sr.id = rm.return_id
                JOIN submission_box sb    ON sb.id = sr.box_id
                JOIN assessment a         ON  a.id = sb.assessment_id
                WHERE {$whereClauseInner}
                AND rm.valid
                AND sr.published_at IS NOT NULL
                GROUP BY sr.owner, sr.box_id, sr.id
            ) t ON t.r_id = rm.return_id
            WHERE {$whereClauseOuter}
            AND rm.valid
            AND sr.published_at IS NOT NULL;
        ");
        $stmt->execute($pb->getParams());
        $studentMarks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Get max mark per criterion and dueWork
        $pb = new Alaress\Database\Predicate\Builder($this->db, '');
        $pb->equals('rc.parent_id', $folderIds)
           ->equals('rc.parent_type', Criterion::PARENT_TYPE_CATEGORY);
        $whereClause = implode(' AND ', $pb->getWhere());
        $stmt        = Database::getInstance()->prepare("
            SELECT rl.box_id AS 'sb.id', rc.id AS 'rc.id', rc.grouping, rc.name, MAX(rd.value) AS 'max_mark'
            FROM rubric_descriptor rd
            JOIN rubric_criterion rc ON rc.id = rd.criterion_id AND rc.deleted_at IS NULL
            JOIN rubric_level rl ON rl.id = rd.level_id AND rl.deleted_at IS NULL
            WHERE {$whereClause}
            GROUP BY rl.box_id, rc.id
            ORDER BY rl.box_id, rc.id
        ");
        $stmt->execute($pb->getParams());
        // 3. Convert each student mark into a normalised value
        $results = [];

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $boxId       = $row['sb.id'];
            $criterionId = $row['rc.id'];

            if (!is_array($results[$boxId])) {
                $results[$boxId] = [];
            }
            $results[$boxId][$criterionId] = [
                'grouping' => $row['grouping'],
                'name'     => $row['name'],
                'maxMark'  => $row['max_mark'],
                'marks'    => [],
            ];
        }

        foreach ($studentMarks as $row) {
            $mark = $row['mark'];
            if (null === $mark) {
                continue;
            }
            $boxId       = $row['sb.id'];
            $criterionId = $row['rc.id'];
            $maxMark     = $results[$boxId][$criterionId]['maxMark'];

            $results[$boxId][$criterionId]['marks'][$row['owner']] = 100.0 * $mark / $maxMark;
        }
        return $results;
    }


    public function populateResultSet(DueWork_Rubric_ResultSet $resultSet, $resultSetID)
    {
        $stmt = $this->db->prepare('SELECT `value`, `label` FROM `rubric_result` WHERE `resultset_id` = :rsid');

        $stmt->execute(['rsid' => $resultSetID]);

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultSet->addResult(new DueWork_Rubric_Result($row['label'], $row['value']));
        }
    }


    /**
     * Get all criteria available on the specified parent
     *
     * @param int[]  $parentIds
     * @param string $parentType
     *
     * @return array
     * @var string   $criteria [rc.id]['grouping']
     * @var string   $criteria [rc.id]['name']
     */
    public function getAvailableCriteria(array $parentIds, $parentType = Criterion::PARENT_TYPE_CATEGORY)
    {
        if (!count($parentIds)) {
            return [];
        }

        $pb = new Alaress\Database\Predicate\Builder($this->db, '');
        $pb->equals('parent_id', $parentIds)
           ->equals('parent_type', $parentType);

        $whereClause = implode(' AND ', $pb->getWhere());

        $stmt = $this->db->prepare("
            SELECT `grouping`, `id`, `name`
            FROM `rubric_criterion`
            WHERE {$whereClause}
            AND deleted_at IS NULL
            ORDER BY `grouping` ASC, `name` ASC
        ");
        $stmt->execute($pb->getParams());

        $criteria = [];
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $criteria[$row['id']] = [
                'grouping' => $row['grouping'],
                'name'     => $row['name'],
            ];
        }
        return $criteria;
    }
}
