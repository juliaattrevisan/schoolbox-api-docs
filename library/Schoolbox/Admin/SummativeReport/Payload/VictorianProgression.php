<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Admin\SummativeReport\Payload;

use DebugBar\DebugBarException;
use Schoolbox\Learning\Assessment\WorkType\Mapper;
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
     * Mega Query containing shortcuts to the parts we need
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
            vaf.published_at
        FROM curriculum_node_for_assessment cnoa
        INNER JOIN curriculum_node cn1 ON cnoa.curriculum_node_id = cn1.id
        LEFT JOIN curriculum_node cn2 ON cn1.curriculum_node_parent_id = cn2.id
        LEFT JOIN assessment a ON cnoa.assessment_id = a.id
        LEFT JOIN v_assessment_feedback vaf ON a.id = vaf.assessment_id
        WHERE a.due_date IS NOT NULL
        AND a.work_type_id = :workTypeId                        -- use the actual one corresponding to Vic thing
        AND vaf.student_id = :studentId                         -- filter to actual student
        AND vaf.published_at IS NOT NULL                        -- only accept published feedbackery
        AND vaf.published_at BETWEEN :startDate AND :endDate    -- specify dates of academic period(s) in use
        ORDER BY cn1.id, a.due_date, vaf.published_at DESC
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
     * @param Mapper $workTypeMapper
     */
    public function __construct(
        private \Schoolbox\Learning\Assessment\WorkType\Mapper $workTypeMapper
    ) {
    }


    /**
     * Set up the necessary parts
     *
     * @return $this
     * @throws \DomainException
     */
    private function setup(): self
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

        return $this;
    }


    /**
     * Generate a payload that finds a particular feedback between $startDate and $endDate
     * corresponding to a Progression Point for a curriculum node
     *
     * @param User $student
     * @param \Alaress_Date_Format $startDate
     * @param \Alaress_Date_Format $endDate
     * @return array
     * @throws DebugBarException
     * @throws \DomainException
     * @throws \PDOException
     */
    public function generate(
        \Schoolbox\User $student,
         \Alaress_Date_Format $startDate,
         \Alaress_Date_Format $endDate
    ): array {
        /* Setup step is mandatory; execute as singleton
         */
        if (!$this->setup) {
            $this->setup();
        }

        $struct = [];

        $rows = $this->payloadStatement->execute([
            'workTypeId' => $this->workTypeId,
            'studentId' => $student->getId(),
            'startDate' => $startDate->getDatabaseDate(),
            'endDate' => $endDate->getDatabaseDate()
        ]);

        while ($row = $rows->fetch()) {
            new \Alaress_Probe($row);
        }

        return $struct;
    }
}