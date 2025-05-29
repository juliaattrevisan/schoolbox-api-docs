<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * CLI Tool
 */
$app = new class extends CliTool\App
{
    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';

    /**
     * --assessment=[integer]
     *
     * The assessment whose Kami data may be corrupted.
     */
    private const OPT_LONG_ASSESSMENT_ID = 'assessment:';

    /**
     * This value is used when setting deleted_at so we can easily identify rows modified by scripts.
     */
    private const SPECIAL_DELETED_AT_VALUE = '1970-01-01 00:00:00';

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_ASSESSMENT_ID,
            self::OPT_LONG_HELP,
        ];
    }

    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            $filename = $this->getToolFilename();
            echo <<<HELP

NAME:
    Kami corrupted data fixer

SYNOPSIS:
    $filename [OPTION]...

DESCRIPTION
    Fixes two types of data corruption found in Kami data.

    Corrupted data type 1:
        Deleted kami_document rows linked to non-deleted student_submission_file rows.
        Solution: Set deleted_at=NULL on that kami_document row.

    Corrupted data type 2:
        Non-deleted kami_document rows having null values for student_submission_file_id and
        worksheet_template_submission_box_id.
        Solution: Mark these rows as deleted (can't do anything else since not enough information)
        Since these rows will not be linked to assessments (as the link would be via the fields
        with null values), this fix will only run if no --assessment parameter has been provided.

OPTIONS
    --assessmentId [integer]
        - Only consider data pertaining to one assessment.
            If not supplied, consider all assessments.
    --perform
        - Include to actually make database changes.
            If not supplied, hypothetical run only.

EXAMPLES
    Find corrupted data in assessment 4:
        fix-corrupted-data.php --assessment=4
    Find and fix corrupted data in assessment 4:
        fix-corrupted-data.php --assessment=4 --perform


HELP;
            return;
        }

        if (!$this->perform()) {
            $this->getLogger()->info('Running in dry-run mode.  Run with the `-p` flag to perform changes.');
        }

        $assessmentId = $this->getOptAsInt(self::OPT_LONG_ASSESSMENT_ID);
        if ($assessmentId) {
            $this->getLogger()->info("Considering only Kami data linked to assessment $assessmentId.");
        }

        try {
            $this->fixCorruptedDataProblem1($assessmentId);

            if ($assessmentId === null) {
                // Data with corrupted data problem 2 cannot be linked to any assessment by definition.
                // So only run this if no assessmentId has been specified.
                $this->fixCorruptedDataProblem2();
            }
        } catch (RuntimeException $e) {
            $this->getLogger()->warning($e->getMessage());
        }
    }

    /**
     * @return bool
     */
    public function runAsRoot(): bool
    {
        return true;
    }

    /**
     * Fix corrupted data problem 1.  See --help text for definition.
     * @param int|null $assessmentId
     */
    private function fixCorruptedDataProblem1(?int $assessmentId): void
    {
        $dbc = $this->getDatabaseInternal();
        $logger = $this->getLogger();

        $query = (
            'SELECT kd.id AS kamiDocumentId, sb.assessment_id AS assessmentId
            FROM kami_document kd
            JOIN student_submission_file ssf ON (kd.student_submission_file_id = ssf.id)
            LEFT JOIN student_submission ss ON (ssf.student_submission_id = ss.id)
            LEFT JOIN submission_box sb ON (ss.box_id = sb.id)
            WHERE kd.deleted_at IS NOT NULL
            AND ssf.deleted_at IS NULL'
        );
        $params = [];

        if ($assessmentId !== null) {
            $query .= "\nAND sb.assessment_id = ?";
            $params[] = $assessmentId;
        }

        // Add ORDER BY to increase readability of logs.
        $query .= "\nORDER BY sb.assessment_id ASC, kd.id ASC";

        $statement = $dbc->executeQuery($query, $params);

        if ($statement->rowCount() === 0) {
            $logger->info('Found 0 deleted kami_document rows linked to undeleted student_submission_file rows.');
            $logger->info('Nothing to do.');
            return;
        }

        $kamiDocumentIdsByAssessmentId = [];
        while ($row = $statement->fetch()) {
            if (!array_key_exists($row['assessmentId'], $kamiDocumentIdsByAssessmentId)) {
                $kamiDocumentIdsByAssessmentId[$row['assessmentId']] = [];
            }
            $kamiDocumentIdsByAssessmentId[$row['assessmentId']][] = $row['kamiDocumentId'];
        }

        $logger->info(
            'Found ' . $statement->rowCount() . ' deleted kami_document rows linked to undeleted' .
            ' student_submission_file rows, in ' . count($kamiDocumentIdsByAssessmentId) .
            ' groups (grouped by assessment).'
        );

        foreach ($kamiDocumentIdsByAssessmentId as $assessmentId => $kamiDocumentIds) {
            if ($this->perform()) {
                $logger->info(
                    'Restoring ' . count($kamiDocumentIds) . ' kami_document rows ' .
                    ($assessmentId === '' ? 'not linked to any assessment' : "for assessment $assessmentId") . '...'
                );
                // NOTE: A UNIQUE key on kami_document ensures that there can only ever be one kami_document row linked
                //       to a student_submission_file row (even after restoring).
                $statement = $dbc->executeQuery(
                    'UPDATE kami_document
                    SET deleted_at = NULL
                    WHERE id IN (' . implode(',', array_map('intval', $kamiDocumentIds)) . ')'
                );
                $logger->info('Done.  ' . $statement->rowCount() . ' kami_document rows affected.');
            } else {
                $logger->info(
                    'Would have undeleted ' . count($kamiDocumentIds) . ' kami_document rows ' .
                    ($assessmentId === '' ? 'not linked to any assessment' : "for assessment $assessmentId") . '.'
                );
            }
            $logger->debug(
                'kami_document.id values for which corrupted data type 1 was fixed: ' .
                implode(',', $kamiDocumentIds)
            );
        }
    }

    /**
     * Fix corrupted data problem 2.  See --help text for definition.
     * Note this type of corrupted data cannot be linked to any assessment by definition.
     */
    private function fixCorruptedDataProblem2(): void
    {
        $dbc = $this->getDatabaseInternal();
        $logger = $this->getLogger();

        $sqlWhereClause = (
            'WHERE deleted_at IS NULL
            AND student_submission_file_id IS NULL
            AND worksheet_template_submission_box_id IS NULL'
        );

        $statement1 = $dbc->executeQuery(
            "SELECT id AS kamiDocumentId
            FROM kami_document kd
            $sqlWhereClause
            ORDER BY id ASC"
        );

        if ($statement1->rowCount() === 0) {
            $logger->info(
                'Found 0 non-deleted kami_document rows not linked to any student_submission_file or' .
                ' worksheet_template_submission_box.'
            );
            $logger->info('Nothing to do.');
            return;
        }

        $logger->info(
            'Found ' . $statement1->rowCount() . ' non-deleted kami_document rows not linked to any' .
            ' student_submission_file or worksheet_template_submission_box.'
        );

        if ($this->perform()) {
            $logger->info('Soft-deleting ' . $statement1->rowCount() . ' unlinked kami_document rows.');
            $statement2 = $dbc->executeQuery(
                // The WHERE clause from above is used here to avoid supplying potentially thousands
                // of values in an IN () clause.  Doing that can exceed MySQL query length constraints.
                "UPDATE kami_document
                SET deleted_at = ?
                $sqlWhereClause",
                [self::SPECIAL_DELETED_AT_VALUE]
            );
            $logger->info('Done.  ' . $statement2->rowCount() . ' kami_document rows affected.');
        } else {
            $logger->info('Would have marked as deleted ' . $statement1->rowCount() . ' unlinked kami_document rows.');
        }

        $kamiDocumentIds = [];
        while ($row = $statement1->fetch()) {
            $kamiDocumentIds[] = $row['kamiDocumentId'];
        }
        $logger->debug(
            'kami_document.id values for which corrupted data type 2 was fixed: ' .
            implode(',', $kamiDocumentIds)
        );
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::INFO, CliKernelAbstract::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options, true))->runApp();
