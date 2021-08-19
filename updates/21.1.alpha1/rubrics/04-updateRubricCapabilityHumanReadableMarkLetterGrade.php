#!/usr/bin/php
<?php

declare(strict_types=1);

use Alaress\Database\Statement\IStatement;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel(
    new class extends CliTool\App {
        /**
         * Query for updating capability marks.
         */
        private IStatement $updateCapabilityMarkQuery;

        /**
         * Query for finding the submission grade for this grade group and
         * percentage value.
         */
        private IStatement $findSubmissionGradeQuery;

        /**
         * Query for finding all capability marks for letter-grade rubrics.
         */
        private IStatement $findCapabilityMarksQuery;

        /**
         * @inheritdoc
         */
        public function run(): void
        {
            // Find capability marks to update
            $this->setupQueries();
            $this->findCapabilityMarksQuery->execute();
            $capabilityMarksCount = $this->findCapabilityMarksQuery->rowCount();
            $this->getLogger()->notice(
                sprintf(
                    '%d marks for due works with capability mark type: letter grade found',
                    $capabilityMarksCount
                )
            );
            if ($capabilityMarksCount === 0) {
                return;
            }

            // Actually update them
            $successCount = 0;
            $failureCount = 0;
            foreach ($this->findCapabilityMarksQuery->fetchAll() as $row) {
                $this->updateCapabilityMark($row)
                    ? $successCount++
                    : $failureCount++;
            }
            $this->getLogger()->notice(
                sprintf(
                    '%d marks updated, %d could not be updated',
                    $successCount,
                    $failureCount
                )
            );
        }

        /**
         * Set up this update script's queries.
         */
        private function setupQueries(): void
        {
            $this->updateCapabilityMarkQuery = $this->getDatabaseInternal()->prepareQuery(
                "
                UPDATE rubric_capability_mark
                SET human_readable_value = :value
                WHERE id = :id
            ");

            $this->findSubmissionGradeQuery = $this->getDatabaseInternal()->prepareQuery(
                "
                SELECT sg.caption
                FROM       submission_grade_group sgg
                INNER JOIN submission_grade       sg  ON sgg.id = sg.submission_grade_group_id
                WHERE sgg.id = :gradeGroupId
                AND   sg.lower_bound <= :percentage
                AND   sgg.deleted_at IS NULL
                AND   sg.deleted_at IS NULL
                ORDER BY sg.lower_bound DESC
                LIMIT 1
            ");

            $this->findCapabilityMarksQuery = $this->getDatabaseInternal()->prepareQuery("
                SELECT
                    rcm.id,
                    rcm.value,
                    rcu.max_value,
                    sb.rubric_capability_grade_group_id AS grade_group_id,
                    sgg.name                            AS grade_group_name
                FROM       rubric_capability_mark  rcm
                INNER JOIN submission_return       sr  ON rcm.return_id = sr.id
                INNER JOIN rubric_capability_usage rcu ON rcm.rubric_capability_id = rcu.rubric_capability_id AND sr.box_id = rcu.submission_box_id
                INNER JOIN submission_box          sb  ON sr.box_id = sb.id
                INNER JOIN submission_mark_type    smt ON sb.rubric_capability_mark_type_id = smt.mark_type_id
                INNER JOIN submission_grade_group  sgg ON sb.rubric_capability_grade_group_id = sgg.id
                WHERE smt.slug = 'letter'
                AND   COALESCE(rcu.max_value, 0) > 0
                ORDER BY rcm.id
            ");
        }

        /**
         * Attempt to populate a capability mark's human-readable value.
         *
         * @param array $row id: the ID of the cap mark record
         *                   value: the current cap mark value
         *                   max_value: the maximum available cap mark value
         *                   grade_group_id, grade_group_name: the ID and name
         *                   of the grade group this due work uses for
         *                   capability marking
         * @return bool
         */
        private function updateCapabilityMark(array $row): bool
        {
            // Skip this row if it has no max value, or a max value <= 0
            if ($row['max_value'] === null) {
                $this->getLogger()->debug(
                    sprintf(
                        'Capability mark #%d: Capability usage record has no max value: skipping',
                        $row['id'],
                    )
                );
                return false;
            }
            if ($row['max_value'] <= 0) {
                $this->getLogger()->debug(
                    sprintf(
                        'Capability mark #%d: Capability usage record max value is not greater than zero: skipping',
                        $row['id'],
                    )
                );
                return false;
            }

            // Translate the percentage to a human-readable mark
            // No human-readable mark found: skip
            $percentage = round($row['value'] / $row['max_value'] * 100, 2);
            $this->findSubmissionGradeQuery->execute([
                'gradeGroupId' => $row['grade_group_id'],
                'percentage'   => $percentage,
            ]);
            $humanReadableMark = $this->findSubmissionGradeQuery->fetchColumn();
            if (($humanReadableMark ?? false) === false) {
                $this->getLogger()->debug(
                    sprintf(
                        'Capability mark #%d: %0.2f / %0.2f (%0.2f%% => %s: <no human-readable mark found>): skipping',
                        $row['id'],
                        $row['value'],
                        $row['max_value'],
                        $percentage,
                        $row['grade_group_name']
                    )
                );
                return false;
            }

            // If this hasn't returned yet, we have a human-readable mark.
            // Update it.
            $this->getLogger()->debug(
                sprintf(
                    'Capability mark #%d: %0.2f / %0.2f (%0.2f%% => %s: %s)',
                    $row['id'],
                    $row['value'],
                    $row['max_value'],
                    $percentage,
                    $row['grade_group_name'],
                    $humanReadableMark
                )
            );

            $this->updateCapabilityMarkQuery->execute([
                'id'    => $row['id'],
                'value' => $humanReadableMark,
            ]);
            return true;
        }
    },
    [
        CliTool\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
        CliTool\Kernel::OPTION_ERRORS => E_ALL,
    ]
))->runApp(true);
