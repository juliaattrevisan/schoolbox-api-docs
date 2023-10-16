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

class Controller extends Base
{
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
     * A list of all the users we actually care about
     * (We don't care about students without feedbacks)
     */
    private const QUERY_USER_LIST = "
        SELECT DISTINCT student_id
        FROM v_assessment_feedback vaf
        INNER JOIN `user` u ON u.id = vaf.student_id
        ORDER BY lastname, firstname
    ";


    /**
     * A repeatable query for each person
     */
    private const QUERY_USER_INFO = '
        SELECT
            vmrf.assessment_id,
            u.firstname,
            u.lastname,
            u.synergy_id,
            h.house,
            lyl.name AS yearLevel
        FROM v_most_recent_feedback vmrf
        INNER JOIN `user` u ON u.id = vmrf.student_id
        LEFT JOIN lu_year_level lyl ON u.year_id = lyl.id
        LEFT JOIN house h on u.house = h.id
        WHERE student_id = :userId
    ';


    /**
     * A repeatable query for each person's assessments
     */
    private const QUERY_ASSESSMENT_INFO = "
       SELECT 
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
        FROM curriculum_node_for_assessment cnof
        INNER JOIN assessment a ON cnof.assessment_id = a.id
        INNER JOIN v_assessment_relation var ON var.assessment_id = a.id
        INNER JOIN v_most_recent_feedback vmrf ON vmrf.assessment_id = a.id AND vmrf.student_id = :userId
        LEFT JOIN curriculum_node cn1 ON cnof.curriculum_node_id = cn1.id
        LEFT JOIN curriculum_node cn2 ON cn2.curriculum_node_parent_id = cn1.id
        LEFT JOIN curriculum_node cn3 ON cn3.curriculum_node_parent_id = cn2.id
        WHERE a.id = :assessmentId
        AND vmrf.published_at >= :from
        AND vmrf.published_at <= :to
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
    private const QUERY_FEEDBACK_TASK = "
        SELECT 
            CASE 
                WHEN completion_status = 3 THEN 100.00,
                WHEN completion_status = 2 THEN 50.00,
                ELSE 0.00
            END AS normalised,
            CASE 
                WHEN completion_status = 3 THEN 'Completed',
                WHEN completion_status = 2 THEN 'Self-assessed',
                ELSE 'Incomplete'
            END AS humanReadable
        FROM task_completion
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
     *
     * @var \Alaress\Database\Statement\Query\Base
     */
    private \Alaress\Database\Statement\Query\Base $userInfoStatement;
    private \Alaress\Database\Statement\Query\Base $assessmentInfoStatement;
    private \Alaress\Database\Statement\Query\Base $dueworkFeedbackStatement;
    private \Alaress\Database\Statement\Query\Base $taskFeedbackStatement;
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
        $this->userInfoStatement = $database->prepareQuery(self::QUERY_USER_INFO);
        $this->assessmentInfoStatement = $database->prepareQuery(self::QUERY_ASSESSMENT_INFO);

        /* Feedback prepared statements
         */
        $this->dueworkFeedbackStatement = $database->prepareQuery(self::QUERY_FEEDBACK_DUEWORK);
        $this->taskFeedbackStatement = $database->prepareQuery(self::QUERY_FEEDBACK_TASK);
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
     */
    public function getExtract(\Alaress_Date_Format $from, \Alaress_Date_Format $to): void
    {
        /* Set up file download thing
         */
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

        /* Find a list of student ids to process one at a time
         */
        $studentManifestStatement = $this->getStudents();

        while ($manifestRow = $studentManifestStatement->fetch(\PDO::FETCH_ASSOC)) {
            $userId = $manifestRow['student_id'];

            /* For every student, get all the stuff they need
             *
             * First obtain their basic user details: name, identifiers, house and year level
             * names - because we're reusing this info per curriculum node and there'll be
             * a lot of those
             */
            $this->userInfoStatement->execute([
                'userId' => $userId
            ]);

            while ($userInfo = $this->userInfoStatement->fetch(\PDO::FETCH_ASSOC)) {
                /* Find all the assessment info from the v_assessment_feedback table
                 * This contains all the info you need to find their marks, for both
                 * the normalised- and human-readable marks
                 */
                $this->assessmentInfoStatement->execute([
                    'assessmentId' => $userInfo['assessment_id'],
                    'userId' => $userId,
                    'from' => $from->getDatabase(),
                    'to' => $to->getDatabase()
                ]);

                /* Find all the curriculum codes associated with the assessments
                 * since you kinda need the full hierarchy of a curriculum node
                 * (Turns out we can just assume the three levels exist correctly)
                 */
                while ($assessmentInfo = $this->assessmentInfoStatement->fetch(\PDO::FETCH_ASSOC)) {
                    /* Find all the ancestry info about the assessment: course, unit
                     */
                    $context = $this->contextLookup->find((int)$assessmentInfo['assessment_id']);

                    /* Feedback data
                     */
                    try {
                        $feedback = $this->findFeedback($assessmentInfo['feedback_table'], (int)$assessmentInfo['feedback_id']);
                    } catch (\DomainException $e) {
                        // noop: skip data that randomly died, for now
                        continue;
                    }

                    /* Combine all that into a csv row
                     */
                    $row = $this->combine($userInfo, $assessmentInfo, $context, $feedback);

                    /* Pump it to an fputcsv
                     */
                    fputcsv($outstream, $row);
                }
            }
        }

        fclose($outstream);
        exit;
    }


    /**
     * Get a bunch of students who have feedbacks
     *
     * @return \Alaress\Database\Statement\Query\Base
     * @throws Exception
     */
    private function getStudents(): \Alaress\Database\Statement\Query\Base
    {
        $studentManifestStatement = $this->database->prepareQuery(self::QUERY_USER_LIST);
        $studentManifestStatement->execute();

        return $studentManifestStatement;
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

            case 'task_completion':
                $this->taskFeedbackStatement->execute([
                    'feedbackId' => $id
                ]);

                return $this->taskFeedbackStatement->fetch(\PDO::FETCH_ASSOC);

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
     * @param array $userInfo
     * @param array $assessmentInfo
     * @param array $context
     * @param array $feedback
     * @return array
     */
    private function combine(array $userInfo, array $assessmentInfo, array $context, array $feedback): array
    {
        return [
            'externalId' => $userInfo['synergy_id'],
            'firstname' => $userInfo['firstname'],
            'lastname' => $userInfo['lastname'],
            'qualification' => $this->renderCurriculumNode((string)$assessmentInfo['Qualification'], (string)$assessmentInfo['QualificationCode']),
            'year' => $userInfo['yearLevel'],
            'house_sb' => $userInfo['house'],
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
