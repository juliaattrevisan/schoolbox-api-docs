<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SummativeReport\Payload\VictorianProgression;

use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\Assessment\Term\Model;
use Schoolbox\Learning\Assessment\WorkType\Mapper;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\User;

/**
 * Victorian Curriculum Reporting Progression Point Visualisation
 * Built for PCC
 */
class Payload
{
    /**
     * Available levels to render in frontend; each int represents its float compatriot
     * e.g. "Level 6" is used as a heading for values 6 and 6.5
     */
    private const LEVELS = [6, 7, 8, 9, 10, 11];
    public const POSSIBLE_OUTCOMES = [6, 6.5, 7, 7.5, 8, 8.5, 9, 9.5, 10, 10.5, 11, 11.5];


    /**
     * Used to specify which nodes to populate with feedback data
     */
    private const TYPE_CURRENT = 'current';
    private const TYPE_PREVIOUS = 'previous';


    /**
     * Mega Query containing shortcuts to the parts we need
     *
     * Note that we can take advantage of the fact that assessments must be either a due work
     * or a quiz, but never both. Brutally LEFT JOIN the feedback tables for each, knowing only
     * one of them can contain data for an assessment. This is probably a better alternative to
     * smashing the db repeatedly
     *
     *     - Work Type Id must correspond to our admin lists; one must have the name defined by Summative Report setting
     *     - assessment feedback must be given to the student specified, not any other student
     *     - assessment feedback must be published (ignore drafts)
     *     - assessment due date must be between dates corresponding within a chosen academic period
     *     - assessment must be using Letter Grade mark type, otherwise they can't choose the values properly
     */
    private const QUERY = "
        SELECT
            cn2.id AS parent_node_id,
            cn2.name AS parent_node,
            cn1.id AS curriculum_node_id,
            cn1.name AS curriculum_node,
            a.id AS assessment_id,
            a.title,
            a.id AS assessment_id,
            a.due_date,
            vmrf.feedback_table,
            vmrf.feedback_id,
            vmrf.student_id,
            vmrf.published_at,
            sr.mark
        FROM curriculum_node_for_assessment cnoa
        INNER JOIN curriculum_node cn1 ON cnoa.curriculum_node_id = cn1.id
        LEFT JOIN curriculum_node cn2 ON cn1.curriculum_node_parent_id = cn2.id
        LEFT JOIN assessment a ON cnoa.assessment_id = a.id
        LEFT JOIN v_most_recent_feedback vmrf ON a.id = vmrf.assessment_id
        LEFT JOIN submission_box sb ON sb.assessment_id = a.id
        LEFT JOIN submission_return sr ON vmrf.feedback_id = sr.id
        WHERE a.due_date IS NOT NULL
        AND a.work_type_id = :workTypeId                        -- use the actual one corresponding to Vic thing
        AND vmrf.student_id = :studentId                        -- filter to actual student
        AND vmrf.published_at IS NOT NULL                       -- only accept published feedbackery
        AND a.due_date BETWEEN :startDate AND :endDate          -- specify dates of academic period(s) in use
        AND sb.submission_grade_group_id IS NOT NULL            -- Letter Grade is required
        AND cn1.deleted_at IS NULL
        AND cn2.deleted_at IS NULL
        AND a.deleted_at IS NULL
        AND sr.deleted_at IS NULL
        ORDER BY cn2.name, cn1.order, a.due_date, vmrf.published_at DESC, vmrf.feedback_id DESC
    ";


    /**
     * A subvariant of the megaquery above, but filtered to a particular class,
     * based on its folder id
     */
    private const CLASS_QUERY = "
        SELECT
            cn2.id AS parent_node_id,
            cn2.name AS parent_node,
            cn1.id AS curriculum_node_id,
            cn1.name AS curriculum_node,
            a.id AS assessment_id,
            a.title,
            a.id AS assessment_id,
            a.due_date,
            vmrf.feedback_table,
            vmrf.feedback_id,
            vmrf.student_id,
            vmrf.published_at,
            sr.mark
        FROM curriculum_node_for_assessment cnoa
        INNER JOIN curriculum_node cn1 ON cnoa.curriculum_node_id = cn1.id
        LEFT JOIN curriculum_node cn2 ON cn1.curriculum_node_parent_id = cn2.id
        LEFT JOIN assessment a ON cnoa.assessment_id = a.id
        LEFT JOIN v_most_recent_feedback vmrf ON a.id = vmrf.assessment_id
        LEFT JOIN submission_box sb ON sb.assessment_id = a.id
        LEFT JOIN submission_return sr ON vmrf.feedback_id = sr.id
        LEFT JOIN mv_assessment_relation mvar ON mvar.assessment_id = a.id
        LEFT JOIN folder f ON mvar.folder_id = f.id
        WHERE a.due_date IS NOT NULL
        AND a.work_type_id = :workTypeId                        -- use the actual one corresponding to Vic thing
        AND vmrf.student_id = :studentId                        -- filter to actual student
        AND vmrf.published_at IS NOT NULL                       -- only accept published feedbackery
        AND a.due_date BETWEEN :startDate AND :endDate          -- specify dates of academic period(s) in use
        AND sb.submission_grade_group_id IS NOT NULL            -- Letter Grade is required
        AND f.id = :folderId
        AND cn1.deleted_at IS NULL
        AND cn2.deleted_at IS NULL
        AND a.deleted_at IS NULL
        AND sr.deleted_at IS NULL
        AND f.deleted_at IS NULL
        ORDER BY cn2.name, cn1.order, a.due_date, vmrf.published_at DESC, vmrf.feedback_id DESC
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
    private \PDOStatement $payloadFolderStatement;


    /**
     * Used to derive a "Rating" string which uses progression points in the current academic
     * period, specifically.
     *
     * @var Rating
     */
    private Rating $rating;


    /**
     * A set of submission state codes that have a specific meaning that isn't currently
     * in a schoolbox instance, due to the varchar(4) limit on submission state codes
     * and 16 chars on submission state display names. Penola needed three places to store
     * data, but we only give them two for now.
     *
     * The human readable variants of DNPP DNPL etc. are in Rating.php as a consequence.
     *
     * @var array
     */
    private array $submissionStateCodes = [];


    /**
     * Constructor.
     *
     * @param Mapper $workTypeMapper
     * @param \Schoolbox\Assessment\Term\Mapper $termMapper
     * @param StateMapper $stateMapper
     * @param int $workTypeId
     * @param bool $annotate
     */
    public function __construct(
        private \Schoolbox\Learning\Assessment\WorkType\Mapper $workTypeMapper,
        private \Schoolbox\Assessment\Term\Mapper $termMapper,
        private \Schoolbox\Learning\DueWork\StateMapper $stateMapper,
        private int $workTypeId,
        private bool $annotate = false
    ) {
    }


    /**
     * Set up the necessary parts
     */
    private function setup(): void
    {
        /* We've already been here, there's nothing to do
         */
        if ($this->setup) {
            return;
        }

        /* Prepare megaquery
         * You end up using one or the other, *or both*; you can't just chop and change because Previous Academic
         * Periods uses a null folder id. It canvasses the entire curriculum for previous progression points.
         */
        $this->payloadStatement = $this->workTypeMapper->getDatabase()->prepare(self::QUERY);
        $this->payloadFolderStatement = $this->workTypeMapper->getDatabase()->prepare(self::CLASS_QUERY);

        /* Find submission states and store them here, for reference
         * THIS LOOKS WEIRD, but there's two naming schemes going on.
         * Penola have historical data with the old scheme (stored in `code`) used for 2023 data,
         * and they've stored the new scheme in `display_as`, which were used for 2024 data.
         * They both co-exist concurrently and have the same meaning.
         */
        $states = $this->stateMapper->findAll();

        $this->submissionStateCodes = array_merge(
            $states->indexBy('code')->keys(),
            $states->indexBy('displayAs')->keys()
        );

        /* We'll be interpreting Submission State marks as Ratings in the chart
         */
        $this->rating = new Rating($this->submissionStateCodes);

        /* Remember we did this
         */
        $this->setup = true;
    }


    /**
     * Facade method for generateOne() and spawns data for the $currentAcademicPeriod
     * provided. It will look back as far as two academic periods before $currentAcademicPeriod
     * for previous data.
     *
     * @param User $student
     * @param Model $currentAcademicPeriod
     * @param int|null $folderId
     * @return array
     * @throws InvalidColumnException
     * @throws \DomainException
     * @throws \PDOException
     */
    public function generate(
        \Schoolbox\User $student,
        \Schoolbox\Assessment\Term\Model $currentAcademicPeriod,
        ?int $folderId
    ): array {
        $studentYearLevel = $this->getYearLevel($student);
        $currentSemester = (int)$this->getCurrentSemester($currentAcademicPeriod);

        /* Collate the current academic period's curriculum nodes
         * and associated feedback
         */
        $data = $this->generateOne(
            self::TYPE_CURRENT,
            $student,
            $studentYearLevel,
            $currentSemester,
            $folderId,
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
            $aggregateAcademicPeriod = (new PreviousAcademicPeriods())->generate($currentAcademicPeriod, $previousAcademicPeriods);

            /* Double-check, for safety; $previousAcademicPeriods might all be archived
             */
            if (null !== $aggregateAcademicPeriod['start'] && null !== $aggregateAcademicPeriod['end']) {
                /* Add the previous academic period's curriculum nodes
                 * and associated feedback, augmenting what we already made
                 */
                $data = $this->generateOne(
                    self::TYPE_PREVIOUS,
                    $student,
                    $studentYearLevel,
                    $currentSemester,
                    $folderId,
                    $aggregateAcademicPeriod['start'],
                    $aggregateAcademicPeriod['end'],
                    $data
                );
            }
        }

        /* Apparently this $struct can come out in some random old order
         * despite the ORDER BY statements; enforce an alphabetical order
         * upon the parent nodes. Also, for consistency between reports.
         */
//        usort($data, static function ($a, $b) {
//            return strnatcasecmp($a['name'], $b['name']);
//        });

        /* The final payload; maybe add extra metadata here if you need it
         */
        return [
            'currentSemester' => $currentSemester,
            'student' => [
                'name' => $student->getFullName(),
                'currentYearLevel' => $studentYearLevel,
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
     * @param int $studentYearLevel
     * @param int $currentSemester
     * @param int|null $folderId
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
        int $studentYearLevel,
        int $currentSemester,
        ?int $folderId,
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
        $params = [
            'workTypeId' => $this->workTypeId,
            'studentId' => $student->getId(),
            /* Go from the $startDate's start of day to the $endDate's end of day
             * Without this, it'll infer '$startDate 00:00:00' to '$endDate 00:00:00'
             * which natively excludes anything that happened after '$endDate 00:00:00',
             * causing it to exclude stuff that happened on this last day.
             *
             * https://stackoverflow.com/questions/5080824/mysql-between-clause-not-inclusive
             */
            'startDate' => $startDate->getDatabaseDate() . ' 00:00:00',
            'endDate' => $endDate->getDatabaseDate() . ' 23:59:59',
        ];

        /* Optional class filter (uses folderId)
         */
        if ($folderId) {
            $params['folderId'] = $folderId;

            $this->payloadFolderStatement->execute($params);
            $statement = $this->payloadFolderStatement;
        } else {
            $this->payloadStatement->execute($params);
            $statement = $this->payloadStatement;
        }

        /* Only find the most recent feedback per assessment, discarding ancient history
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $parentNodeId = $row['parent_node_id'];
            $nodeId = $row['curriculum_node_id'];

            /* Construct parent node first
             */
            if (null === $parentNodeId) {
                if (!isset($struct[$nodeId])) {
                    $struct[$nodeId] = [
                        'id' => $nodeId,
                        'name' => trim((string)$row['curriculum_node']),
                        'rating' => null,
                        self::TYPE_CURRENT => null,
                        self::TYPE_PREVIOUS => null,
                        'subnodes' => []
                    ];
                }
            } elseif (!isset($struct[$parentNodeId])) {
                $struct[$parentNodeId] = [
                    'id' => $parentNodeId,
                    'name' => trim((string)$row['parent_node']),
                    'rating' => null,
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
                    'rating' => null,
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
            if (null === $parentNodeId) {
                if (null === $struct[$nodeId][$type]) {
                    /* There is no parent node
                     */
                    $struct[$nodeId][$type] = $outcome;
                } else {
                    /* Deal with submission state (it takes precedence; never heard of someone getting a submission
                     * state then having it overturned by actually doing the work they were supposed to do?)
                     */
                    if (\in_array($row['mark'], $this->submissionStateCodes, true)) {
                        $struct[$nodeId][$type] = $outcome;
                    } else {
                        /* Remember the biggest mark this person received for this given $nodeId
                         * Update existing record
                         */
                        if ((float)$struct[$nodeId][$type]['mark'] < (float)$row['mark']) {
                            $struct[$nodeId][$type] = $outcome;
                        }
                    }
                }

                /* Update rating for this curriculum node, using the "current" node
                 */
                if (self::TYPE_CURRENT === $type) {
                    $struct[$nodeId]['rating'] = $this->rating->rate($studentYearLevel, $currentSemester, (string)$struct[$nodeId][$type]['mark'], $this->annotate);
                }
            } else {
                if (null === $struct[$parentNodeId]['subnodes'][$nodeId][$type]) {
                    /* There is a parent node
                     */
                    $struct[$parentNodeId]['subnodes'][$nodeId][$type] = $outcome;
                } else {
                    /* Remember the biggest mark this person received for this given ($parentNodeId, $nodeId)
                     * Update existing record
                     *
                     * Deal with submission state (it takes precedence; never heard of someone getting a submission
                     * state then having it overturned by actually doing the work they were supposed to do?)
                     */
                    if (\in_array($row['mark'], $this->submissionStateCodes, true)) {
                        $struct[$parentNodeId]['subnodes'][$nodeId][$type] = $outcome;
                    } else {
                        if ((float)$struct[$parentNodeId]['subnodes'][$nodeId][$type]['mark'] < (float)$row['mark']) {
                            $struct[$parentNodeId]['subnodes'][$nodeId][$type] = $outcome;
                        }
                    }
                }

                /* Update rating for this curriculum node, using the "current" node
                 */
                if (self::TYPE_CURRENT === $type) {
                    $struct[$parentNodeId]['subnodes'][$nodeId]['rating'] = $this->rating->rate($studentYearLevel, $currentSemester, (string)$struct[$parentNodeId]['subnodes'][$nodeId][$type]['mark'], $this->annotate);
                }
            }
        }

        return $struct;
    }


    /**
     * Figure out if we're in semester 1 or 2, of the given $currentAcademicPeriod
     *
     * This presumes an Academic Period uses the format "yyyy-semesternum" e.g. "2024-2"
     *
     * @param \Schoolbox\Assessment\Term\Model $currentAcademicPeriod
     * @return string|null
     */
    private function getCurrentSemester(\Schoolbox\Assessment\Term\Model $currentAcademicPeriod): ?string
    {
        $currentYear = date('Y');

        /* Note that people have mistakenly used _ instead of - in PCC
         */
        preg_match("/{$currentYear}([-_]+)(\d+)/", $currentAcademicPeriod->getCode(), $matches);

        /* Current Semester could not be determined; leave empty
         * Otherwise send back either 1 or 2 (anything else will be ignored in frontend land)
         */
        return $matches[2] ?? null;
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
