<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Sedavic\Extract\Cli;

use Alaress\Database\Exception;
use Alaress\Database\Statement\Query\IQuery;
use Custom\Sedavic\Extract\ContextLookup;
use Schoolbox\Application\CliTool;

class ExtractTool extends CliTool\App
{
    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * --from
     *
     * An example arg that has an actual value, denoted by the colon
     */
    private const OPT_LONG_FROM = 'from:';


    /**
     * --to
     *
     * An example arg that has an actual value, denoted by the colon
     */
    private const OPT_LONG_TO = 'to:';


    /**
     * --max
     *
     * An example arg that has an actual value, denoted by the colon
     */
    private const OPT_LONG_MAX_ASSESSMENTS = 'max:';


    /**
     * Header row in the csv, as per the jira
     * It's probably not exactly what they're after...but ok
     */
    private const HEADINGS = [
//        'Assessment Spec Id',
//        'Assessment Impl Id',
        'Synergetic ID',
        'Firstname',
        'Lastname',
        'Qualification',
        'Year Level',
        'Class (House)',
        'Course (SB)',
        'Unit (SB)',
        'Assessment',
        'Unit',
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
            asi.specification_assessment_id AS assessmentSpecId,
            asi.implementation_assessment_id AS assessmentImplId,
            u.synergy_id AS externalId,
            u.firstname,
            u.lastname,
            h.house,
            lyl.name AS yearLevel,
            ai.title,
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
        ORDER BY asi.specification_assessment_id, lastname, firstname
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
    private const QUERY_FEEDBACK_TASK = "
        SELECT
            CASE
                WHEN completion_status = 3 THEN 100.00
                WHEN completion_status = 2 THEN 50.00
                ELSE 0.00
            END AS normalised,
            CASE
                WHEN completion_status = 3 THEN 'Completed'
                WHEN completion_status = 2 THEN 'Self-assessed'
                ELSE 'Incomplete'
            END AS humanReadable
        FROM task_completion
        WHERE id = :feedbackId
        LIMIT 1
    ";


    /**
     * Find a particular feedback here
     * Interestingly, max_mark being 0 results in mark/max_mark equating to null
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
     * All the classes' codes that had an assessment descended from the given
     * assessment spec id - you need this to build a list of guys who were meant
     * to have done these assessments
     */
    private const QUERY_CLASSES = "
        SELECT DISTINCT fc.code
        FROM assessment_specification_implementation asi
        INNER JOIN assessment a ON asi.implementation_assessment_id = a.id
        INNER JOIN mv_assessment_relation mvar ON a.id = mvar.assessment_id
        INNER JOIN folder_code fc ON mvar.folder_id = fc.folder_id
        WHERE asi.specification_assessment_id = :assessmentSpecId
        AND a.deleted_at IS NULL
    ";


    /**
     * Find schoolbox fields yearLevel and house, given an externalId
     *
     * FIXME probably need to do an check on /group Role ids here in case
     *  a student has the same externalId as a staff - or can we assume
     *  this isn't happening at this particular school?
     */
    private const QUERY_USER_DETAIL = "
        SELECT
            h.house,
            lyl.name AS yearLevel
        FROM `user` u
        LEFT JOIN lu_year_level lyl ON u.year_id = lyl.id
        LEFT JOIN house h ON u.house = h.id
        WHERE u.synergy_id = :externalId
    ";


    /**
     * Prepared statements. For some reason the typehints tell you
     * it's an IQuery, but it's actually a one of these...what kind
     * of sloanish witchery is this??
     */
    private \Alaress\Database\Statement\Query\Base|IQuery $assessmentInfoStatement;
    private \Alaress\Database\Statement\Query\Base|IQuery $nodeStatement;
    private \Alaress\Database\Statement\Query\Base|IQuery $dueworkFeedbackStatement;
    private \Alaress\Database\Statement\Query\Base|IQuery $taskFeedbackStatement;
    private \Alaress\Database\Statement\Query\Base|IQuery $quizFeedbackStatement;
    private \Alaress\Database\Statement\Query\Base|IQuery $classesStatement;
    private \Alaress\Database\Statement\Query\Base|IQuery $userDetailStatement;


    /**
     * @var \External_Class
     */
    private \External_Class $externalClass;


    /**
     * @var ContextLookup
     */
    private ContextLookup $contextLookup;


    /**
     * A cache of everyone whose in a particular folder code
     * to dodge around some synergetic latency
     *
     * @var array
     */
    private array $classList = [];


    /**
     * A list of random problems with their data
     *
     * @var array
     */
    private array $problems = [];


    /**
     * The number of rows we obtained
     *
     * @var int
     */
    private int $numRows = 0;


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_MAX_ASSESSMENTS,
            self::OPT_LONG_FROM,
            self::OPT_LONG_TO,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

SEDAVIC Extract

Finds all the curriculum nodes and assessment specification titles for everyone
who was supposed to be receiving feedback for those specifications' implementations.
This was part of an auditing procedure and generates a csv

Usage: extract.php --from=... --to=... [--max=...] > outfile.csv
        --from      Start date of extraction e.g. 2023-01-01
        --to        End date of extraction e.g. 2023-12-12

        --max       Maximum number of assessments to process before arbitrarily exiting
                    (used for debugging faster)


HELP;
            return;
        }

        $database = $this->getDatabaseInternal();

        /* Other dependencies
         */
        $this->externalClass = new \External_Class();

        /* To figure out course names from their spec or impl
         * Carefully abducted from Learnosity prototype
         */
        $this->contextLookup = new ContextLookup($database);

        /* Basic user and assessment metadata
         */
        $this->assessmentInfoStatement = $this->getDatabaseInternal()->prepareQuery(self::QUERY_ASSESSMENT_INFO);
        $this->nodeStatement = $database->prepareQuery(self::QUERY_CURRICULUM_NODES);

        /* Feedback prepared statements
         */
        $this->dueworkFeedbackStatement = $database->prepareQuery(self::QUERY_FEEDBACK_DUEWORK);
        $this->taskFeedbackStatement = $database->prepareQuery(self::QUERY_FEEDBACK_TASK);
        $this->quizFeedbackStatement = $database->prepareQuery(self::QUERY_FEEDBACK_QUIZ);

        /* Class list manifests
         */
        $this->classesStatement = $database->prepareQuery(self::QUERY_CLASSES);
        $this->userDetailStatement = $database->prepareQuery(self::QUERY_USER_DETAIL);

        /* Get the dates out
         */
        $from = new \Alaress_Date_Format($this->getOpt(self::OPT_LONG_FROM), 'Y-m-d');
        $to = new \Alaress_Date_Format($this->getOpt(self::OPT_LONG_TO), 'Y-m-d');

        /* Set up file download thing
         */
        $outstream = fopen("php://stdout", 'w');

        /* Start with header row
         */
        fputcsv($outstream, self::HEADINGS, escape: '');

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

        $studentList = null;
        $lastAssessmentInfo = null;

        /* Artificial circuit breaker
         */
        $nAssessments = 0;
        $maxAssessments = (int)$this->getOpt(self::OPT_LONG_MAX_ASSESSMENTS);

        while ($assessmentInfo = $this->assessmentInfoStatement->fetch(\PDO::FETCH_ASSOC)) {
            /* For every assessment spec,
             * find all the classes that have a corresponding assessment implementation,
             * get a list of guys from Synergetic and collate them for each class,
             * then subtract anyone with feedback from that list of guys.
             * The remainder are people who should have done that assessment but never received feedback
             * and we pump them onto the end of the list per assessment spec
             *
             * Maintain a static list of externalIds in case we need to reuse them
             * from the previous iteration.
             */
            if (null !== $lastAssessmentInfo && $lastAssessmentInfo['assessmentSpecId'] !== $assessmentInfo['assessmentSpecId']) {
                $this->backfillUnmarked($lastAssessmentInfo, $assessmentInfo, $studentList, $outstream);

                /* Reset student list for next assessment
                 */
                $studentList = null;

                /* Artificial circuit breaker
                 */
                $nAssessments++;

                if ($maxAssessments && $nAssessments >= $maxAssessments) {
                    $this->getLogger()->info('Exiting due to maximum assessments limit being reached.');
                    break;
                }
            }

            /* Find the list of students for this assessment spec id and then subtract guys from it
             * when their feedbacks are encountered
             */
            if (null === $studentList) {
                $studentList = $this->findStudents((int)$assessmentInfo['assessmentSpecId'], (int)$assessmentInfo['assessmentImplId']);
            }

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
            } catch (\DomainException | \RuntimeException $e) {
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
            $this->outputRows($rows, $outstream);

            /* Remember we saw this guy
             */
            unset($studentList[(string)$assessmentInfo['externalId']]);

            /* Get ready for next iteration
             */
            $lastAssessmentInfo = $assessmentInfo;
        }

        /* Anyone unmarked after the last iteration
         * $studentList will be null if broken via max assess limit, and this
         * won't be needed anyway
         */
        if (isset($studentList)) {
            $this->backfillUnmarked($lastAssessmentInfo, null, $studentList, $outstream);
        }

        /* Gather some stats
         */
        $end = microtime(true);

        if ($this->perform()) {
            fclose($outstream);
        } else {
            $problems = array_keys($this->problems);

            foreach ($problems as $problem) {
                $this->getLogger()->warning($problem);
            }

            $this->getLogger()->warning(sprintf(
                '%s seconds for %s rows of data',
                $end - $start,
                $this->numRows
            ));
        }

        exit;
    }


    /**
     * Everyone in $studentList left standing needs to be added with empty placeholder values.
     *
     * @param array $lastAssessmentInfo
     * @param array|null $assessmentInfo
     * @param array $studentList
     * @param $outstream
     * @return void
     * @throws Exception
     * @throws \RuntimeException
     */
    private function backfillUnmarked(array $lastAssessmentInfo, ?array $assessmentInfo, array $studentList, $outstream): void
    {
        $this->getLogger()->debug(sprintf(
            'Was previously looking at %s; now looking at %s.',
            $lastAssessmentInfo['assessmentSpecId'],
            $assessmentInfo['assessmentSpecId'] ?? 'last iteration'
        ));

        /* Find everyone who didn't get a feedback for $lastAssessmentInfo['assessmentSpecId'],
         * find their context/curriculum nodes,
         * and push them through the same combine() method
         */

        /* Find all the ancestry info about the assessment: course, unit
         */
        $context = $this->contextLookup->find((int)$lastAssessmentInfo['assessment_id']);

        /* Feedback data
         */
        $feedback = [
            'normalised' => '',
            'humanReadable' => '',
        ];

        /* Curriculum nodes
         */
        $curriculumNodes = $this->findCurriculumNodes((int)$lastAssessmentInfo['assessmentSpecId']);

        foreach ($studentList as $externalId => $data) {
            $this->getLogger()->debug(sprintf(
                'Backfilling unfeedbacked student %s %s %s',
                $externalId,
                $data['firstname'],
                $data['lastname']
            ));

            /* Manufacture a placeholder payload containing students' data for
             * those who didn't get feedback
             */
            $temp = $data;
            $temp['title'] = $lastAssessmentInfo['title'];

            /* Combine all that into a set of csv rows
             */
            $rows = $this->combine($temp, $curriculumNodes, $context, $feedback);

            /* Pump it to an fputcsv
             */
            $this->outputRows($rows, $outstream);
        }
    }


    /**
     * Dunno the type definition of fopen("php://output", 'r');
     *
     * @param array $rows
     * @param mixed $outstream
     * @return void
     */
    private function outputRows(array $rows, mixed $outstream): void
    {
        foreach ($rows as $row) {
            fputcsv($outstream, $row, escape: '');

            $this->numRows++;
        }
    }


    /**
     * For the given assessment spec id,
     * Find all its implementations and folders that contain said implementations,
     * then find all the guys in each of those homepages
     *
     * These are the list of people who are SUPPOSED to have received feedback for
     * the assessments that descended from the given assessment spec
     *
     * @param int $assessmentSpecId
     * @param int $assessmentImplId
     * @return array
     * @throws Exception
     */
    private function findStudents(int $assessmentSpecId, int $assessmentImplId): array
    {
        $this->classesStatement->execute([
            'assessmentSpecId' => $assessmentSpecId
        ]);

        $studentList = [];

        while ($folder = $this->classesStatement->fetch(\PDO::FETCH_ASSOC)) {
            $folderCode = $folder['code'];

            /* If we never encountered $folderCode's list of students,
             * go find them and remember their scalar data
             */
            if (!isset($this->classList[$folderCode])) {
                /* Initialise array element
                 */
                $this->classList[$folderCode] = [];

                /* Find the students in each folder code
                 */
                $students = $this->externalClass->getStudents($folderCode);

                foreach ($students as $student) {
                    $externalId = (string)$student['synergy_id'];

                    /* Find house and year level from their schoolbox account
                     * since seda hijacked these fields for their own purposes
                     */
                    $this->userDetailStatement->execute([
                        'externalId' => $externalId
                    ]);

                    $user = $this->userDetailStatement->fetch(\PDO::FETCH_ASSOC);

                    /* Add this student info into the stack, uniquely, by externalId
                     * Probably need to do a separate query to find out their house
                     * in schoolbox.user though.
                     */
                    $studentDetails = [
//                        'assessmentSpecId' => $assessmentSpecId,
//                        'assessmentImplId' => $assessmentImplId,
                        'externalId' => $externalId,
                        'firstname' => $student['firstname'],
                        'lastname' => $student['lastname'],
                        'house' => $user['house'],
                        'yearLevel' => $user['yearLevel']
                    ];

                    $this->classList[$folderCode][$externalId] = $studentDetails;
                }
            }

            /* Add it to the crew
             */
            $studentList = array_merge($studentList, $this->classList[$folderCode]);
        }

        /* You'd think that array_merge or array_merge_recursive would preserve keys
         * but it doesn't. For whatever reason I have to compile this manually?
         */
        $indexed = [];

        foreach ($studentList as $student) {
            $indexed[$student['externalId']] = $student;
        }

        return $indexed;
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

        /* Warning when something is amiss
         */
        if (!$this->perform() && !\count($stack)) {
            $message = sprintf(
                'Assessment <a href="/learning/assessments/%s/modify">%s</a> is probably missing curriculum nodes',
                $assessmentId,
                $assessmentId
            );

            $this->problems[$message] = true;
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
            if ((string)$leaf['parentId'] === $parentId) {
                $children = $this->buildTree($leaves, (string)$leaf['id']);

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
//                        'assessmentSpecId' => $assessmentInfo['assessmentSpecId'],
//                        'assessmentImplId' => $assessmentInfo['assessmentImplId'],
                        'externalId' => $assessmentInfo['externalId'],
                        'firstname' => $assessmentInfo['firstname'],
                        'lastname' => $assessmentInfo['lastname'],
                        'qualification' => $this->renderCurriculumNode((string)$qualificationData['name'], (string)$qualificationData['code']),
                        'yearLevel' => $assessmentInfo['yearLevel'],
                        'house_sb' => $assessmentInfo['house'],
                        'course_sb' => $context['course']['name'],
                        'unit_sb' => $context['unit']['name'],
                        'assessment' => $assessmentInfo['title'],
                        'unit' => $this->renderCurriculumNode((string)$unitData['name'], (string)$unitData['code']),
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


    /**
     * FIXME Decide whether this script must be run as root
     *  By default, this returns false (see CliTool\App) but if you're using methods that require
     *  access to special folders (such as when you're creating files) you need to run this as root.
     *  Otherwise it'll fail with a generic error that might mislead you
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return parent::runAsRoot();
    }
}
