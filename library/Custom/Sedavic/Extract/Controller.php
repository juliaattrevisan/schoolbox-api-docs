<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Sedavic\Extract;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception;
use Alaress\Database\Statement\Query\IQuery;
use Alaress\Web\Controller\Base;
use DebugBar\DebugBarException;

class Controller extends Base
{
    /**
     * Skip generating a file, instead printing data to browzer
     *
     * @var bool
     */
    private const PERFORM = true;


    /**
     * Specify filename format with the two dates in it
     */
    private const FILENAME = 'extract-%s_%s.csv';


    /**
     * Header row in the csv, as per the jira
     * It's probably not exactly what they're after...but ok
     */
    private const HEADINGS = [
        'Synergetic ID',
        'Firstname',
        'Lastname',
        'Qualification',
        'Year Level',
        'Class (House)',
        'Unit',
        'Course (SB)',
        'Unit (SB)',
        'Assessment',
        'Area of Study/Outcome',
        'Result (S/N)',
        'Result (Raw)'
    ];


    /**
     * A repeatable query for each person's assessments
     *
     * For some oddball reason I need to specify collation here, because
     * our views + random text strings are an illegal mix somehow
     *
     * Error Code: 1267. Illegal mix of collations (utf8mb4_unicode_ci,COERCIBLE) and (utf8mb4_general_ci,COERCIBLE)
     */
    private const QUERY_ASSESSMENT_INFO = "
       SELECT
            u.synergy_id,
            u.firstname,
            u.lastname,
            h.house,
            lyl.name AS yearLevel,
            ai.title,
            asi.specification_assessment_id AS assessmentSpecId,
            var.*,
            vmrf.feedback_id,
            vmrf.feedback_table,
            vmrf.published_at
        FROM v_most_recent_feedback vmrf
        INNER JOIN assessment ai ON vmrf.assessment_id = ai.id
        INNER JOIN assessment_specification_implementation asi ON ai.id = asi.implementation_assessment_id
        INNER JOIN `user` u ON u.id = vmrf.student_id 
        LEFT JOIN lu_year_level lyl ON u.year_id = lyl.id
        LEFT JOIN house h ON u.house = h.id
        LEFT JOIN mv_assessment_relation var ON var.assessment_id = ai.id
        WHERE vmrf.published_at >= :from
        AND vmrf.published_at <= :to
        AND ai.deleted_at IS NULL
        AND u.deleted_at IS NULL
        ORDER BY lastname, firstname
    ";


    /**
     * These are obtained separately so you can compute the node hierarchy
     * outside of sql, since they appear in an arbitrary order. You could
     * assume a sequence circumstantially based on the way we import our
     * curriculum nodes, though; ordering by id DESC. Child node ids must
     * be larger than parent node ids and they were always created in a
     * hierarchy, never flat.
     */
    private const QUERY_CURRICULUM_NODES = "
        SELECT 
            cn.id,
            cn.name,
            cn.code,
            cnp.id AS parentId,
            cnp.name AS parentName,
            cnp.code AS parentCode
        FROM curriculum_node_for_assessment cnof 
        INNER JOIN curriculum_node cn ON cnof.curriculum_node_id = cn.id
        INNER JOIN curriculum_node cnp ON cn.curriculum_node_parent_id = cnp.id
        WHERE assessment_id = :assessmentId
        AND cn.deleted_at IS NULL
        AND cnp.deleted_at IS NULL
        ORDER BY cn.curriculum_node_parent_id
    ";


    /**
     * Find a particular feedback here
     */
    private const QUERY_FEEDBACK_DUEWORK = "
        SELECT 
            norm_mark AS normalised,
            mark AS humanReadable
        FROM submission_return
        WHERE id = :feedbackId
        LIMIT 1
    ";


    /**
     * Find a particular feedback here
     */
    private const QUERY_FEEDBACK_QUIZ = "
        SELECT 
            norm_mark AS normalised,
            mark AS humanReadable
        FROM assess_attempt
        WHERE attempt_id = :feedbackId
        LIMIT 1
    ";


    /**
     * Prepared statements. For some reason the typehints tell you
     * it's an IQuery, but it's actually a one of these...what kind
     * of sloanish witchery is this??
     */
    private \Alaress\Database\Statement\Query\Base|IQuery $assessmentInfoStatement;
    private \Alaress\Database\Statement\Query\Base|IQuery $nodeStatement;
    private \Alaress\Database\Statement\Query\Base|IQuery $dueworkFeedbackStatement;
    private \Alaress\Database\Statement\Query\Base|IQuery $quizFeedbackStatement;


    /**
     * @var ContextLookup
     */
    private ContextLookup $contextLookup;


    /**
     * Controller constructor.
     */
    public function __construct(private IConnector $database)
    {
        /* To figure out course names from their spec or impl
         * Carefully abducted from Learnosity prototype
         */
        $this->contextLookup = new ContextLookup($this->database);

        /* Basic user and assessment metadata
         */
        $this->assessmentInfoStatement = $database->prepareQuery(self::QUERY_ASSESSMENT_INFO);
        $this->nodeStatement = $database->prepareQuery(self::QUERY_CURRICULUM_NODES);

        /* Feedback prepared statements
         */
        $this->dueworkFeedbackStatement = $database->prepareQuery(self::QUERY_FEEDBACK_DUEWORK);
        $this->quizFeedbackStatement = $database->prepareQuery(self::QUERY_FEEDBACK_QUIZ);
    }


    /**
     * For each student, get all their assessment info and feedback data
     * and pump it into a csv.
     *
     * The date range provided relates to when the feedbacks were published.
     *
     * @param \Alaress_Date_Format $from
     * @param \Alaress_Date_Format $to
     * @return void
     * @throws Exception
     * @throws DebugBarException
     */
    public function getExtract(\Alaress_Date_Format $from, \Alaress_Date_Format $to): void
    {
        $outstream = null;
        $numRows = 0;

        /* Set up file download thing
         */
        /* @phpstan-ignore-next-line */
        if (self::PERFORM) {
            $filename = sprintf(
                self::FILENAME,
                $from->getDatabaseDate(),
                $to->getDatabaseDate()
            );

            header('Content-type: text/csv; charset=UTF-8');
            header("Content-disposition: attachment; filename=\"{$filename}\"");

            $outstream = fopen("php://output", 'r');

            /* Start with header row
             */
            fputcsv($outstream, self::HEADINGS);
        }

        /* Find a list of student ids to process one at a time
         * For every student, get all the stuff they need
         *
         * First obtain their basic user details: name, identifiers, house and year level
         * names - because we're reusing this info per curriculum node and there'll be
         * a lot of those
         *
         * Find all the curriculum codes associated with the assessments
         * since you kinda need the full hierarchy of a curriculum node
         * (Turns out we can just assume the three levels exist correctly)
         */
        $this->assessmentInfoStatement->execute([
            'from' => $from->getDatabase(),
            'to' => $to->getDatabase()
        ]);

        /* Find all the assessment info from the v_most_recent_feedback table
         * This contains all the info you need to find their marks, for both
         * the normalised- and human-readable marks
         */
        $start = microtime(true);

        while ($assessmentInfo = $this->assessmentInfoStatement->fetch(\PDO::FETCH_ASSOC)) {
            try {
                /* Find all the ancestry info about the assessment: course, unit
                 */
                $context = $this->contextLookup->find((int)$assessmentInfo['assessment_id']);

                /* Feedback data
                 */
                $feedback = $this->findFeedback($assessmentInfo['feedback_table'], (int)$assessmentInfo['feedback_id']);

                /* Curriculum nodes
                 */
                $curriculumNodes = $this->findCurriculumNodes((int)$assessmentInfo['assessmentSpecId']);
            } catch (\DomainException|\RuntimeException $e) {
                /* noop: skip data that randomly died, for now
                 *
                 *   - Assessment Feedback must be one of Due Work, Task or Quiz feedbacks;
                 *   - Assessments must be in a course to be included in this extract;
                 */
                continue;
            }

            /* Combine all that into a set of csv rows
             */
            $rows = $this->combine($assessmentInfo, $curriculumNodes, $context, $feedback);

            /* Pump it to an fputcsv
             */
            /* @phpstan-ignore-next-line */
            if (self::PERFORM) {
                foreach ($rows as $row) {
                    fputcsv($outstream, $row);
                }
            } else {
                foreach ($rows as $row) {
                    $numRows++;
                    new \Alaress_Probe($row);
                }
            }
        }

        $end = microtime(true);

        /* @phpstan-ignore-next-line */
        if (self::PERFORM) {
            fclose($outstream);
        } else {
            new \Alaress_Probe(sprintf(
                '%s seconds for %s rows of data',
                $end - $start,
                $numRows
            ));
        }

        exit;
    }


    /**
     * Find the assigned nodes for an assessment,
     * plus one parent node that isn't assigned
     *
     * @param int $assessmentId
     * @return array
     * @throws Exception
     */
    private function findCurriculumNodes(int $assessmentId): array
    {
        $this->nodeStatement->execute([
            'assessmentId' => $assessmentId
        ]);

        $stack = [];

        while ($unitRow = $this->nodeStatement->fetch(\PDO::FETCH_ASSOC)) {
            /* Uppermost node (inferred)
             */
            if ('' === $unitRow['parentCode']) {
                $stack[] = [
                    'id' => $unitRow['parentId'],
                    'name' => $unitRow['parentName'],
                    'code' => $unitRow['parentCode'],
                    'parentId' => ''
                ];
            }

            $stack[] = $unitRow;
        }

        return $this->buildTree($stack);
    }


    /**
     * Recursively build the tree of curriculum nodes
     *
     * @param array $leaves
     * @param string $parentId
     * @return array
     */
    private function buildTree(array $leaves, string $parentId = ''): array
    {
        $tree = [];

        foreach ($leaves as &$leaf) {
            if ($leaf['parentId'] === $parentId) {
                $children = $this->buildTree($leaves, $leaf['id']);

                if ($children) {
                    $leaf['children'] = $children;
                }

                $tree[(int)$leaf['id']] = $leaf;
                unset($leaf);
            }
        }

        return $tree;
    }


    /**
     * Figure out where the feedback lives, and get its normalised- and human-readable
     * marks together. Feedbacks live in different tables contextually and we only
     * ever need a particular feedback once.
     *
     * @param string $table
     * @param int $id
     * @return array
     * @throws Exception
     * @throws \DomainException
     */
    private function findFeedback(string $table, int $id): array
    {
        switch ($table) {
            case 'submission_return':
                $this->dueworkFeedbackStatement->execute([
                    'feedbackId' => $id
                ]);

                return $this->dueworkFeedbackStatement->fetch(\PDO::FETCH_ASSOC);

            case 'assess_attempt':
                $this->quizFeedbackStatement->execute([
                    'feedbackId' => $id
                ]);

                return $this->quizFeedbackStatement->fetch(\PDO::FETCH_ASSOC);

            default:
                throw new \DomainException(sprintf(
                    'Unknown feedback table %s',
                    $table
                ));
        }
    }


    /**
     * Take all the pieces we collected and voltron them into Its Final Form
     *
     * @param array $assessmentInfo
     * @param array $curriculumNodes
     * @param array $context
     * @param array $feedback
     * @return array
     */
    private function combine(array $assessmentInfo, array $curriculumNodes, array $context, array $feedback): array
    {
        $stack = [];

        foreach ($curriculumNodes as $qualificationId => $qualificationData) {
            foreach ($qualificationData['children'] as $unitId => $unitData) {
                foreach ($unitData['children'] as $outcomeId => $outcomeData) {
                    $assessmentRow =  [
                        'externalId' => $assessmentInfo['synergy_id'],
                        'firstname' => $assessmentInfo['firstname'],
                        'lastname' => $assessmentInfo['lastname'],
                        'qualification' => $this->renderCurriculumNode((string)$qualificationData['name'], (string)$qualificationData['code']),
                        'year' => $assessmentInfo['yearLevel'],
                        'house_sb' => $assessmentInfo['house'],
                        'unit' => $this->renderCurriculumNode((string)$unitData['name'], (string)$unitData['code']),
                        'course_sb' => $context['course']['name'],
                        'unit_sb' => $context['unit']['name'],
                        'assessment' => $assessmentInfo['title'],
                        'aos' => $this->renderCurriculumNode((string)$outcomeData['name'], (string)$outcomeData['code']),
                        'normalised' => $feedback['normalised'],
                        'humanReadable' => $feedback['humanReadable'],
                    ];

                    $stack[] = $assessmentRow;
                }
            }
        }

        return $stack;
    }


    /**
     * Render curriculum nodes consistently,
     * accounting for maybe broken data
     *
     * @param string $name
     * @param string $code
     * @return string
     */
    private function renderCurriculumNode(string $name, string $code): string
    {
        /* Code is sometimes empty
         */
        if ('' !== trim($name)) {
            if ('' !== trim($code)) {
                return sprintf('%s [%s]', $name, $code);
            }

            return $name;
        }

        return '';
    }
}
