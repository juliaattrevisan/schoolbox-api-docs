<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
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
    Fixes three types of data corruption found in Kami data.

    Corrupted data type 1:
        Deleted kami_document rows linked to non-deleted student_submission_file rows.
        Solution: Set deleted_at=NULL on that kami_document row.

    Corrupted data type 2:
        Non-deleted kami_document rows not linked to submission_box or student_submission_file.
        Solution: Mark these rows as deleted (can't do anything else since not enough information)

    Corrupted data type 3:
        Non-deleted submission_return rows linked to deleted student_submission rows.
        Solution:
        For each non-deleted submission_return row linked to a deleted student_submission row...
         * Find the student_submission row that was most recently submitted at the time the submission_return row was
           created.
         * If found, link the non-deleted submission_return row to that row.
         * Else make note of problem not fixed.

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
//            $this->fixCorruptedDataProblem1($assessmentId);
            $this->fixCorruptedDataProblem2($assessmentId);
//            $this->fixCorruptedDataProblem3($assessmentId);
        } catch (RuntimeException $e) {
            $this->getLogger()->warning($e->getMessage());
        }
    }

    /**
     * @return bool
     */
    public function runAsRoot(): bool
    {
        return false;
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
            JOIN student_submission ss ON (ssf.student_submission_id = ss.id)
            JOIN submission_box sb ON (ss.box_id = sb.id)
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
            ' student_submission_file rows, in ' . count($kamiDocumentIdsByAssessmentId) . ' assessments.'
        );

        foreach ($kamiDocumentIdsByAssessmentId as $assessmentId => $kamiDocumentIds) {
            if ($this->perform()) {
                $logger->info(
                    'Undeleting ' . count($kamiDocumentIds) . " kami_document rows for assessment $assessmentId..."
                );
                // NOTE: A UNIQUE key on kami_document ensures that there can only ever be one kami_document row linked
                //       to a student_submission_file row (even after undeleting).
                $statement = $dbc->executeQuery(
                    'UPDATE kami_document
                    SET deleted_at = NULL
                    WHERE id IN (' . implode(',', array_map('intval', $kamiDocumentIds)) . ')'
                );
                $logger->info('Done.  ' . $statement->rowCount() . ' kami_document rows affected.');
            } else {
                $logger->info(
                    'Would have undeleted ' . count($kamiDocumentIds) .
                    " kami_document rows for assessment $assessmentId."
                );
            }
            $logger->debug('kamiDocumentIds: ' . implode(',', $kamiDocumentIds));
        }
    }

    /**
     * Fix corrupted data problem 2.  See --help text for definition.
     * @param int|null $assessmentId
     */
    private function fixCorruptedDataProblem2(?int $assessmentId): void
    {
        $dbc = $this->getDatabaseInternal();
        $logger = $this->getLogger();

        $query = (
            'SELECT kd.id AS kamiDocumentId, sb.assessment_id AS assessmentId
            FROM kami_document kd
            JOIN student_submission_file ssf ON (kd.student_submission_file_id = ssf.id)
            JOIN student_submission ss ON (ssf.student_submission_id = ss.id)
            JOIN submission_box sb ON (ss.box_id = sb.id)
            WHERE kd.deleted_at IS NULL
            AND kd.student_submission_file_id IS NULL
            AND kd.worksheet_template_submission_box_id IS NULL'
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
            $logger->info(
                'Found 0 non-deleted kami_document rows not linked to any student_submission_file or' .
                ' worksheet_template_submission_box.'
            );
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
            'Found ' . $statement->rowCount() . ' non-deleted kami_document rows not linked to any' .
            ' student_submission_file or worksheet_template_submission_box, in ' .
            count($kamiDocumentIdsByAssessmentId) . ' assessments.'
        );

        foreach ($kamiDocumentIdsByAssessmentId as $assessmentId => $kamiDocumentIds) {
            if ($this->perform()) {
                $logger->info(
                    'Deleting ' . count($kamiDocumentIds) .
                    " unlinked kami_document rows for assessment $assessmentId..."
                );
                $statement = $dbc->executeQuery(
                    'UPDATE kami_document
                    SET deleted_at = NOW()
                    WHERE id IN (' . implode(',', array_map('intval', $kamiDocumentIds)) . ')'
                );
                $logger->info('Done.  ' . $statement->rowCount() . ' kami_document rows affected.');
            } else {
                $logger->info(
                    'Would have deleted ' . count($kamiDocumentIds) .
                    " unlinked kami_document rows for assessment $assessmentId."
                );
            }
            $logger->debug('kamiDocumentIds: ' . implode(',', $kamiDocumentIds));
        }
    }

    /**
     * Fix corrupted data problem 3.  See --help text for definition.
     * @param int|null $assessmentId
     *
     * Non-deleted submission_return rows linked to deleted student_submission rows.
     * Solution:
     * For each non-deleted submission_return row linked to a deleted student_submission row...
     * Find the student_submission row that was most recently submitted at the time the submission_return row was
     * created.
     * If found, link the non-deleted submission_return row to that row.
     * Else make note of problem not fixed.
     */
    private function fixCorruptedDataProblem3(?int $assessmentId): void
    {
        $dbc = $this->getDatabaseInternal();
        $logger = $this->getLogger();

        $query = (
            'SELECT
            sr.id AS submissionReturnId,
            sr.created_at AS submissionReturnCreatedAt,
            sr.owner AS submissionReturnOwnerUserId,
            sr.student_submission_id AS submissionReturnStudentSubmissionId,
            sb.assessment_id AS assessmentId
            FROM submission_return sr
            JOIN student_submission ss ON (sr.student_submission_id = ss.id)
            JOIN submission_box sb ON (ss.box_id = sb.id)
            WHERE sr.deleted_at IS NULL
            AND ss.deleted_at IS NOT NULL
            AND EXISTS(
                SELECT *
                FROM kami_document kd
                JOIN student_submission_file ssf ON (kd.student_submission_file_id = ssf.id)
                WHERE ssf.student_submission_id = ss.id
            )'
        );
        $params = [];

        if ($assessmentId !== null) {
            $query .= "\nAND sb.assessment_id = ?";
            $params[] = $assessmentId;
        }

        // Add ORDER BY to increase readability of logs.
        $query .= "\nORDER BY sb.assessment_id ASC, sr.id ASC";
        $statement = $dbc->executeQuery($query, $params);

        if ($statement->rowCount() === 0) {
            $logger->info('Found 0 non-deleted submission_return rows linked to deleted student_submission rows.');
            $logger->info('Nothing to do.');
            return;
        }

        $submissionReturnRowsByAssessmentId = [];
        while ($row = $statement->fetch()) {
            if (!array_key_exists($row['assessmentId'], $submissionReturnRowsByAssessmentId)) {
                $submissionReturnRowsByAssessmentId[$row['assessmentId']] = [];
            }
            $submissionReturnRowsByAssessmentId[$row['assessmentId']][] = [
                'id' => $row['submissionReturnId'],
                'createdAt' => $row['submissionReturnCreatedAt'],
                'ownerUserId' => $row['submissionReturnOwnerUserId'],
                'studentSubmissionId' => $row['submissionReturnStudentSubmissionId'],
            ];
        }

        $logger->info(
            'Found ' . $statement->rowCount() . ' non-deleted submission_return rows linked to deleted student_submission rows,' .
            ' in ' . count($submissionReturnRowsByAssessmentId) . ' assessments.'
        );

        foreach ($submissionReturnRowsByAssessmentId as $assessmentId => $submissionReturnRows) {
            $logger->info(
                'Considering ' . count($submissionReturnRows) .
                " submission_return rows linked to assessment $assessmentId."
            );
            foreach ($submissionReturnRows as $submissionReturnRow) {
                $logger->info(
                    " * Considering submission_return {$submissionReturnRow['id']}," .
                    " linked to deleted student_submission {$submissionReturnRow['studentSubmissionId']}."
                );
                $statement = $dbc->executeQuery(
                    // Find the student_submission row that was most recently
                    // submitted at the time the submission_return row was created.
                    'SELECT ss.id
                    FROM student_submission ss
                    JOIN submission_box sb ON (ss.box_id = sb.id)
                    WHERE sb.assessment_id = :assessmentId
                    AND ss.owner = :ownerUserId
                    AND ss.submitted_at < :submissionReturnCreatedAt
                    AND ss.deleted_at IS NULL
                    ORDER BY ss.submitted_at DESC',
                    [
                        'assessmentId' => $assessmentId,
                        'ownerUserId' => $submissionReturnRow['ownerUserId'],
                        'submissionReturnCreatedAt' => $submissionReturnRow['createdAt'],
                    ]
                );
                $logger->info(
                    '   Found ' . $statement->rowCount() . ' candidate student_submission rows to link to instead.'
                );
                if ($statement->rowCount() > 0) {
                    $chosenStudentSubmissionId = $statement->fetch()['id'];
                    if ($this->perform()) {
                    } else {
                        $logger->info(
                            "   Would have linked submission_return {$submissionReturnRow['id']} to" .
                            " student_submission $chosenStudentSubmissionId."
                        );
                    }
                }
            }
        }
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::DEBUG, CliKernelAbstract::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
