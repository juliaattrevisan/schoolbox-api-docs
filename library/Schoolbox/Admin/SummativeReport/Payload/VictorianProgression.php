<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SummativeReport\Payload;

use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;

/**
 * Victorian Curriculum Reporting Progression Point Visualisation
 * Built for PCC
 */
class VictorianProgression
{
    /**
     * This worktype is required
     */
    private const WORK_TYPE_NAME = 'Victorian Curriculum Progression Points';


    /**
     * Used to specify which nodes to populate with feedback data
     */
    private const TYPE_CURRENT = 'current';
    private const TYPE_PREVIOUS = 'previous';


    /**
     * Available levels to render in frontend; each int represents its float compatriot
     * e.g. "Level 6" is used as a heading for values 6 and 6.5
     */
    private const LEVELS = [6, 7, 8, 9, 10];
    private const POSSIBLE_OUTCOMES = [6, 6.5, 7, 7.5, 8, 8.5, 9, 9.5, 10, 10.5];


    /**
     * Mega Query containing shortcuts to the parts we need
     *
     * Note that we can take advantage of the fact that assessments must be either a due work
     * or a quiz, but never both. Brutally LEFT JOIN the feedback tables for each, knowing only
     * one of them can contain data for an assessment. This is probably a better alternative to
     * smashing the db repeatedly
     */
    private const QUERY = "
        SELECT
            cn2.id AS parent_node_id,
            cn2.name AS parent_node,
            cn1.id AS curriculum_node_id,
            cn1.name AS curriculum_node,
            a.id AS assessment_id,
            a.title,
            a.due_date,
            vaf.feedback_table,
            vaf.feedback_id,
            vaf.student_id,
            vaf.published_at,
            COALESCE(sr.mark, aa.mark) AS mark
        FROM curriculum_node_for_assessment cnoa
        INNER JOIN curriculum_node cn1 ON cnoa.curriculum_node_id = cn1.id
        LEFT JOIN curriculum_node cn2 ON cn1.curriculum_node_parent_id = cn2.id
        LEFT JOIN assessment a ON cnoa.assessment_id = a.id
        LEFT JOIN v_assessment_feedback vaf ON a.id = vaf.assessment_id
        LEFT JOIN submission_box sb ON sb.assessment_id = a.id AND feedback_table = 'submission_return'
        LEFT JOIN submission_return sr ON vaf.feedback_id = sr.id AND feedback_table = 'submission_return'
        LEFT JOIN assess quiz ON quiz.assessment_id = a.id AND feedback_table = 'assess_attempt'
        LEFT JOIN assess_attempt aa ON vaf.feedback_id = aa.attempt_id AND feedback_table = 'assess_attempt'
        WHERE a.due_date IS NOT NULL
        AND a.work_type_id = :workTypeId                        -- use the actual one corresponding to Vic thing
        AND vaf.student_id = :studentId                         -- filter to actual student
        AND vaf.published_at IS NOT NULL                        -- only accept published feedbackery
        AND vaf.published_at BETWEEN :startDate AND :endDate    -- specify dates of academic period(s) in use
        AND (
            sb.submission_grade_group_id IS NOT NULL            -- Letter Grade is required
            OR quiz.grade_group_id IS NOT NULL
        )  
        ORDER BY cn2.name, cn1.order, a.due_date, vaf.published_at DESC
    ";


    /**
     * Remember when setup() was run - we only need it once
     *
     * @var bool
     */
    private bool $setup = false;


    /**
     * We only need this once, I guess
     *
     * @var \PDOStatement
     */
    private \PDOStatement $payloadStatement;


    /**
     * @var int|null
     */
    private ?int $workTypeId = null;


    /**
     * Constructor.
     *
     * @param \Schoolbox\Learning\Assessment\WorkType\Mapper $workTypeMapper
     * @param \Schoolbox\Assessment\Term\Mapper $termMapper
     */
    public function __construct(
        private \Schoolbox\Learning\Assessment\WorkType\Mapper $workTypeMapper,
        private \Schoolbox\Assessment\Term\Mapper $termMapper
    ) {
    }


    /**
     * Set up the necessary parts
     *
     * @throws \DomainException
     */
    private function setup(): void
    {
        /* We've already been here, there's nothing to do
         */
        if ($this->setup) {
            return;
        }

        /* Prepare megaquery
         */
        $this->payloadStatement = $this->workTypeMapper->getDatabase()->prepare(self::QUERY);

        /* Find the Work Type Id first, by name for lack of a better way
         */
        try {
            $workType = $this->workTypeMapper->findOneByColumn('name', self::WORK_TYPE_NAME);
            $this->workTypeId = (int)$workType->getId();
        } catch (\Schoolbox\Mapper\Exception $e) {
            throw new \DomainException(sprintf(
                'Missing required worktype in admin: %s',
                self::WORK_TYPE_NAME
            ));
        }

        /* Remember we did this
         */
        $this->setup = true;
    }


    /**
     * Facade method for generateOne()
     *
     * @param User $student
     * @return array
     * @throws Exception
     * @throws InvalidColumnException
     * @throws \DomainException
     * @throws \PDOException
     */
    public function generate(\Schoolbox\User $student): array
    {
        $currentAcademicPeriod = $this->termMapper->findForDate(\Alaress_Date_Format::now());

        /* Collate the current academic period's curriculum nodes
         * and associated feedback
         */
        $data = $this->generateOne(
            self::TYPE_CURRENT,
            $student,
            $currentAcademicPeriod->getStart(),
            $currentAcademicPeriod->getDisplayFinish()
        );

        /* Start digging into the past academic period for a person's previous Progression Point
         */
        $previousAcademicPeriods = $this->termMapper->findAllNonExtraordinary();

        /* Only one academic period? There's no previous one, just the current one.
         * No trabajo. Otherwise, get it directly from the collection lol
         */
        if ($previousAcademicPeriods->count() > 1) {
            $previousAcademicPeriod = $previousAcademicPeriods->getOffset(1);

            /* FIXME!!!!!!!!! For testing due to lack of data
             */
            $previousAcademicPeriod = $this->termMapper->findById(4);

            /* Add the previous academic period's curriculum nodes
             * and associated feedback, augmenting what we already made
             */
            $data = $this->generateOne(
                self::TYPE_PREVIOUS,
                $student,
                $previousAcademicPeriod->getStart(),
                $previousAcademicPeriod->getDisplayFinish(),
                $data
            );
        }

        /* The final payload; maybe add extra metadata here if you need it
         */
        return [
            'student' => [
                'name' => $student->getFullName(),
                'currentYearLevel' => $this->getYearLevel($student),
            ],
            'levels' => self::LEVELS,
            'possibleOutcomes' => self::POSSIBLE_OUTCOMES,
            'data' => $data
        ];
    }


    /**
     * Generate a payload that finds a particular feedback between $startDate and $endDate
     * corresponding to a Progression Point for a curriculum node
     *
     * Augments $struct, if provided
     *
     * @param string $type
     * @param User $student
     * @param \Alaress_Date_Format $startDate
     * @param \Alaress_Date_Format $endDate
     * @param array $struct
     * @return array
     * @throws \DomainException
     * @throws \PDOException
     */
    private function generateOne(
        string $type,
        \Schoolbox\User $student,
         \Alaress_Date_Format $startDate,
         \Alaress_Date_Format $endDate,
        array $struct = []
    ): array {
        /* Setup step is mandatory; execute as singleton
         */
        if (!$this->setup) {
            $this->setup();
        }

        /* Only accept valid types
         */
        if (self::TYPE_CURRENT !== $type && self::TYPE_PREVIOUS !== $type) {
            throw new \DomainException(sprintf(
                'Unknown type: %s. Must be either %s or %s',
                $type,
                self::TYPE_CURRENT,
                self::TYPE_PREVIOUS
            ));
        }

        /* Let's go
         */
        $this->payloadStatement->execute([
            'workTypeId' => $this->workTypeId,
            'studentId' => $student->getId(),
            'startDate' => $startDate->getDatabaseDate(),
            'endDate' => $endDate->getDatabaseDate()
        ]);

        while ($row = $this->payloadStatement->fetch(\PDO::FETCH_ASSOC)) {
            $parentNodeId = $row['parent_node_id'];
            $nodeId = $row['curriculum_node_id'];

            /* Construct parent node first
             */
            if (null === $parentNodeId) {
                if (!isset($struct[$nodeId])) {
                    $struct[$nodeId] = [
                        'id' => $nodeId,
                        'name' => trim((string)$row['curriculum_node']),
                        self::TYPE_CURRENT => null,
                        self::TYPE_PREVIOUS => null,
                    ];
                }
            } elseif (!isset($struct[$parentNodeId])) {
                $struct[$parentNodeId] = [
                    'id' => $parentNodeId,
                    'name' => trim((string)$row['parent_node']),
                    /* A subnode, if extant;
                     * each subnode will contain marks if it exists
                     */
                    'subnodes' => [],
                ];
            }

            /* Add any subnodes if they exist
             */
            if ($parentNodeId && !isset($struct[$parentNodeId]['subnodes'][$nodeId])) {
                $struct[$parentNodeId]['subnodes'][$nodeId] = [
                    'id' => $nodeId,
                    'name' => trim((string)$row['curriculum_node']),
                    self::TYPE_CURRENT => null,
                    self::TYPE_PREVIOUS => null,
                ];
            }

            /* Build the outcome struct, because that's just too handy
             */
            $outcome = [
                'mark' => $row['mark'],
                'feedback_table' => $row['feedback_table'],
                'feedback_id' => $row['feedback_id'],
            ];

            /* Pop it into the node specified by $type
             * Note that $row['mark'] will be something in 6, 6.5, 7, 7.5, 8, 8.5...etc
             *
             * If something was already here, don't touch it; this ensures we get
             * the most recently published one and skipping all the older ones
             */
            if (null === $parentNodeId && null === $struct[$nodeId][$type]) {
                /* There is no parent node
                 */
                $struct[$nodeId][$type] = $outcome;
            } elseif (null === $struct[$parentNodeId]['subnodes'][$nodeId][$type]) {
                /* There is a parent node
                 */
                $struct[$parentNodeId]['subnodes'][$nodeId][$type] = $outcome;
            }
        }

        return $struct;
    }


    /**
     * Figure out the student's year level numeric value so we can stripe the ui
     * Year Level names are user configurable so we have to play "find the int"
     *
     * No year level? We die.
     *
     * @param User $student
     * @return int
     * @throws \DomainException
     */
    private function getYearLevel(\Schoolbox\User $student): int
    {
        preg_match('/\d+/', (string)$student->getYear()?->getName(), $matches);

        $currentYearLevel = $matches[0] ?? null;

        if (null === $currentYearLevel) {
            throw new \DomainException('Unknown year level for this student.');
        }

        return (int)$currentYearLevel;
    }
}
