<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SummativeReport\Payload;

use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
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
        LEFT JOIN submission_return sr ON vaf.feedback_id = sr.id AND feedback_table = 'submission_return'
        LEFT JOIN assess_attempt aa ON vaf.feedback_id = aa.attempt_id AND feedback_table = 'assess_attempt'
        WHERE a.due_date IS NOT NULL
        AND a.work_type_id = :workTypeId                        -- use the actual one corresponding to Vic thing
        AND vaf.student_id = :studentId                         -- filter to actual student
        AND vaf.published_at IS NOT NULL                        -- only accept published feedbackery
        AND vaf.published_at BETWEEN :startDate AND :endDate    -- specify dates of academic period(s) in use
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

        $this->setup = true;
    }


    /**
     * Facade method for generateOne()
     *
     * @param User $student
     * @return array
     * @throws \DomainException
     * @throws \PDOException
     * @throws Exception
     * @throws Deleted
     */
    public function generate(\Schoolbox\User $student): array
    {
        /* FIXME replace with actual way of finding the answer
         */
        $currentAcademicPeriod = $this->termMapper->findById(4);
        $previousAcademicPeriod = $this->termMapper->findById(4);

        /* Collate the current academic period's curriculum nodes
         * and associated feedback
         */
        $result = $this->generateOne(
            self::TYPE_CURRENT,
            $student,
            $currentAcademicPeriod->getStart(),
            $currentAcademicPeriod->getDisplayFinish()
        );

        /* Add the previous academic period's curriculum nodes
         * and associated feedback, augmenting what we already made
         */
        return $this->generateOne(
            self::TYPE_PREVIOUS,
            $student,
            $previousAcademicPeriod->getStart(),
            $previousAcademicPeriod->getDisplayFinish(),
            $result
        );
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
            $nodeId = $row['curriculum_node_id'];

            /* Init if it wasn't already here
             */
            if (!isset($struct[$nodeId])) {
                $struct[$nodeId] = [
                    'node' => [
                        'id' => $nodeId,
                        'name' => trim((string)$row['curriculum_node']),
                        'parent' => [
                            'id' => $row['parent_node_id'],
                            'name' => trim((string)$row['parent_node'])
                        ]
                    ],
                    self::TYPE_CURRENT => null,
                    self::TYPE_PREVIOUS => null
                ];
            }

            /* Pop it into the node specified by $type
             */
            $struct[$nodeId][$type] = $row['mark'];
        }

        return $struct;
    }
}