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
            a.title,
            var.*,
            cn1.name AS Qualification,
            cn1.code AS QualificationCode,
            cn2.name AS Unit,
            cn2.code AS UnitCode,
            cn3.name AS AreaOfStudy,
            cn3.code AS AreaOfStudyCode,
            vmrf.feedback_id,
            vmrf.feedback_table,
            vmrf.published_at
        FROM v_most_recent_feedback vmrf
        INNER JOIN assessment a ON vmrf.assessment_id = a.id
        INNER JOIN `user` u ON u.id = vmrf.student_id 
        LEFT JOIN lu_year_level lyl ON u.year_id = lyl.id
        LEFT JOIN house h ON u.house = h.id
        LEFT JOIN curriculum_node_for_assessment cnof ON cnof.assessment_id = a.id
        LEFT JOIN curriculum_node cn1 ON cnof.curriculum_node_id = cn1.id
        LEFT JOIN curriculum_node cn2 ON cn2.curriculum_node_parent_id = cn1.id
        LEFT JOIN curriculum_node cn3 ON cn3.curriculum_node_parent_id = cn2.id
        LEFT JOIN mv_assessment_relation var ON var.assessment_id = a.id
        WHERE vmrf.published_at >= :from
        AND vmrf.published_at <= :to
        AND a.deleted_at IS NULL
        AND u.deleted_at IS NULL
        ORDER BY lastname, firstname
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

//   Temporarily disabled while I get requirements from Newman
//
//    /**
//     * Find a particular feedback here
//     */
//    private const QUERY_FEEDBACK_TASK = "
//        SELECT
//            CASE
//                WHEN completion_status = 3 THEN 100.00,
//                WHEN completion_status = 2 THEN 50.00,
//                ELSE 0.00
//            END AS normalised,
//            CASE
//                WHEN completion_status = 3 THEN 'Completed',
//                WHEN completion_status = 2 THEN 'Self-assessed',
//                ELSE 'Incomplete'
//            END AS humanReadable
//        FROM task_completion
//        WHERE id = :feedbackId
//        LIMIT 1
//    ";


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
     *
     * @var \Alaress\Database\Statement\Query\Base
     */
    private \Alaress\Database\Statement\Query\Base $assessmentInfoStatement;
    private \Alaress\Database\Statement\Query\Base $dueworkFeedbackStatement;
//    private \Alaress\Database\Statement\Query\Base $taskFeedbackStatement;
    private \Alaress\Database\Statement\Query\Base $quizFeedbackStatement;


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
        $this->contextLookup = new ContextLookup($database);

        /* Basic user and assessment metadata
         */
        $this->assessmentInfoStatement = $database->prepareQuery(self::QUERY_ASSESSMENT_INFO);

        /* Feedback prepared statements
         */
        $this->dueworkFeedbackStatement = $database->prepareQuery(self::QUERY_FEEDBACK_DUEWORK);
//        $this->taskFeedbackStatement = $database->prepareQuery(self::QUERY_FEEDBACK_TASK);
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
            } catch (\DomainException | \RuntimeException $e) {
                /* noop: skip data that randomly died, for now
                 *
                 *   - Assessment Feedback must be one of Due Work, Task or Quiz feedbacks;
                 *   - Assessments must be in a course to be included in this extract;
                 */
                continue;
            }

            /* Combine all that into a csv row
             */
            $row = $this->combine($assessmentInfo, $context, $feedback);

            /* Pump it to an fputcsv
             */
            if (self::PERFORM) {
                fputcsv($outstream, $row);
            } else {
                $numRows++;
                new \Alaress_Probe($row);
            }
        }

        $end = microtime(true);

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

//            case 'task_completion':
//                $this->taskFeedbackStatement->execute([
//                    'feedbackId' => $id
//                ]);
//
//                return $this->taskFeedbackStatement->fetch(\PDO::FETCH_ASSOC);

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
     * @param array $context
     * @param array $feedback
     * @return array
     */
    private function combine(array $assessmentInfo, array $context, array $feedback): array
    {
        return [
            'externalId' => $assessmentInfo['synergy_id'],
            'firstname' => $assessmentInfo['firstname'],
            'lastname' => $assessmentInfo['lastname'],
            'qualification' => $this->renderCurriculumNode((string)$assessmentInfo['Qualification'], (string)$assessmentInfo['QualificationCode']),
            'year' => $assessmentInfo['yearLevel'],
            'house_sb' => $assessmentInfo['house'],
            'unit' => $this->renderCurriculumNode((string)$assessmentInfo['Unit'], (string)$assessmentInfo['UnitCode']),
            'course_sb' => $context['course']['name'],
            'unit_sb' => $context['unit']['name'],
            'assessment' => $assessmentInfo['title'],
            'aos' => $this->renderCurriculumNode((string)$assessmentInfo['AreaOfStudy'], (string)$assessmentInfo['AreaOfStudyCode']),
            'normalised' => $feedback['normalised'],
            'humanReadable' => $feedback['humanReadable'],
        ];
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
        if ('' !== trim($name) && '' !== trim($code)) {
            return sprintf('%s [%s]', $name, $code);
        }

        return '';
    }
}
