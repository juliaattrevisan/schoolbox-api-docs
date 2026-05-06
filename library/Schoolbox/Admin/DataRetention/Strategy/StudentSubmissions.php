<?php

/**
 * Student Submissions, retain soft deleted data for a certain time.
 *
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Strategy;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Admin\DataRetention\ValidStrats;
use Schoolbox\Config\ConfigMapper;

class StudentSubmissions extends Base
{
    protected string $retentionConfigKey = ConfigMapper::NAME_DATA_RETENTION_STUDENTSUBMISSIONS;

    private const string TABLE_NAME_SUBMISSION = 'student_submission';
    private const string TABLE_NAME_SUBMISSION_FILE = 'student_submission_file';
    private const string TABLE_NAME_TURNITIN_REPORT = 'turnitin_report';
    private const string TABLE_NAME_PLAGSCAN_DOCUMENT = 'plagscan_document';
    private const string TABLE_NAME_KAMI_DOCUMENT = 'kami_document';

    /**
     * @inheritDoc
     */
    public function getRetentionLabel(): string
    {
        return 'Student Submissions Retention';
    }

    /**
     * @inheritDoc
     */
    public function getStrategyName(): string
    {
        return ValidStrats::STUDENTSUBMISSIONS;
    }

    /**
     * @inheritDoc
     */
    protected function hasDataToDelete(): bool
    {
        $query = "
            WITH eligible_submissions AS (
                SELECT id
                FROM " . self::TABLE_NAME_SUBMISSION . "
                WHERE deleted_at IS NOT NULL AND deleted_at < :old
            ),
            eligible_submission_files AS (
                SELECT id
                FROM " . self::TABLE_NAME_SUBMISSION_FILE . "
                WHERE student_submission_id IN (SELECT id FROM eligible_submissions)
            )
            SELECT
                (SELECT COUNT(*) FROM eligible_submissions) AS submissions,
                (SELECT COUNT(*) FROM eligible_submission_files) AS submission_files,
                (SELECT COUNT(*) FROM " . self::TABLE_NAME_TURNITIN_REPORT . " WHERE student_submission_file_id IN (SELECT id FROM eligible_submission_files)) AS turnitin_reports,
                (SELECT COUNT(*) FROM " . self::TABLE_NAME_PLAGSCAN_DOCUMENT . " WHERE student_submission_file_id IN (SELECT id FROM eligible_submission_files)) AS plagscan_documents,
                (SELECT COUNT(*) FROM " . self::TABLE_NAME_KAMI_DOCUMENT . " WHERE student_submission_file_id IN (SELECT id FROM eligible_submission_files)) AS kami_documents
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'old' => $this->calculateDate(),
        ]);

        $toDelete = $statement->fetch();

        if (empty($toDelete['submissions'])) {
            $this->log->info("Found '0' student submissions to delete");
            return false;
        }

        $this->log->info(sprintf("Found '%s' student submissions to delete", $toDelete['submissions']));
        $this->log->info(sprintf("With '%s' student submission files to delete", $toDelete['submission_files']));
        $this->log->info(sprintf("With '%s' turnitin reports to delete", $toDelete['turnitin_reports']));
        $this->log->info(sprintf("With '%s' plagscan documents to delete", $toDelete['plagscan_documents']));
        $this->log->info(sprintf("With '%s' kami documents to delete", $toDelete['kami_documents']));

        return true;
    }

    /**
     * @inheritDoc
     */
    protected function getIdsToDelete(): array
    {
        $this->log->info('Deleting student submissions older than ' . $this->calculateDate());

        $query = "
            SELECT id
            FROM " . self::TABLE_NAME_SUBMISSION . "
            WHERE deleted_at IS NOT NULL AND deleted_at < :old
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'old' => $this->calculateDate(),
        ]);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @inheritDoc
     */
    protected function deleteChunk(array $chunk): void
    {
        $submissionIds = $this->normalizeIds(array_column($chunk, 'id'));
        if ($submissionIds === []) {
            return;
        }

        $submissionFileIds = $this->normalizeIds($this->findSubmissionFileIds($submissionIds));

        $this->log->debug(sprintf("Deleting student_submission ids: %s", implode(', ', $submissionIds)));
        if ($submissionFileIds !== []) {
            $this->log->debug(sprintf("Deleting student_submission_file ids: %s", implode(', ', $submissionFileIds)));
        }

        $turnitinReportsDeleted = 0;
        $plagscanDocumentsDeleted = 0;
        $kamiDocumentsDeleted = 0;
        $submissionFilesDeleted = 0;

        if ($submissionFileIds !== []) {
            $turnitinReportsDeleted = $this->deleteTurnitinReports($submissionFileIds);
            $plagscanDocumentsDeleted = $this->deletePlagscanDocuments($submissionFileIds);
            $kamiDocumentsDeleted = $this->deleteKamiDocuments($submissionFileIds);
            $submissionFilesDeleted = $this->deleteSubmissionFiles($submissionFileIds);
        }

        $submissionsDeleted = $this->deleteSubmissions($submissionIds);

        if ($this->perform) {
            $this->log->notice(
                sprintf(
                    "Deleted '%s' student submissions, '%s' student submission files, '%s' turnitin reports, '%s' plagscan documents, '%s' kami documents",
                    $submissionsDeleted,
                    $submissionFilesDeleted,
                    $turnitinReportsDeleted,
                    $plagscanDocumentsDeleted,
                    $kamiDocumentsDeleted,
                )
            );
        } else {
            $this->log->notice(
                sprintf(
                    "Would have deleted '%s' student submissions, '%s' student submission files, '%s' turnitin reports, '%s' plagscan documents, '%s' kami documents",
                    $submissionsDeleted,
                    $submissionFilesDeleted,
                    $turnitinReportsDeleted,
                    $plagscanDocumentsDeleted,
                    $kamiDocumentsDeleted,
                )
            );
        }
    }

    /**
     * Normalizes PDO/string ids into a stable positive integer set so repeated
     * ids or driver-specific fetch types do not affect generated SQL.
     *
     * @param array<int, int|string> $ids
     * @return array<int, int>
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = array_map(static fn(int|string $id): int => (int) $id, $ids);
        $normalized = array_values(array_unique(array_filter($normalized, static fn(int $id): bool => $id > 0)));

        sort($normalized);

        return $normalized;
    }

    /**
     * Resolves files from parent submissions because every file attached to an
     * eligible submission should be removed regardless of the file deleted_at.
     *
     * @param array<int, int> $submissionIds
     * @return array<int, int>
     * @throws \PDOException
     */
    protected function findSubmissionFileIds(array $submissionIds): array
    {
        if ($submissionIds === []) {
            return [];
        }

        $placeholders = Builder::getArrayPlaceholders($submissionIds, 'student_submission_id');
        $statement = $this->getPreparedStatement(
            "SELECT id FROM " . self::TABLE_NAME_SUBMISSION_FILE . " WHERE student_submission_id IN {$placeholders}"
        );
        $statement->execute(Builder::getArrayParameters($submissionIds, 'student_submission_id'));

        return array_column($statement->fetchAll(\PDO::FETCH_ASSOC), 'id');
    }

    /**
     * Deletes Turnitin rows before submission files because the schema holds a
     * direct foreign key from reports to student_submission_file.id.
     *
     * @param array<int, int> $fileIds
     * @return int
     * @throws \PDOException
     */
    protected function deleteTurnitinReports(array $fileIds): int
    {
        return $this->deleteRowsByStudentSubmissionFileId(self::TABLE_NAME_TURNITIN_REPORT, $fileIds);
    }

    /**
     * Deletes PlagScan rows before submission files because this table also
     * references student_submission_file.id directly.
     *
     * @param array<int, int> $fileIds
     * @return int
     * @throws \PDOException
     */
    protected function deletePlagscanDocuments(array $fileIds): int
    {
        return $this->deleteRowsByStudentSubmissionFileId(self::TABLE_NAME_PLAGSCAN_DOCUMENT, $fileIds);
    }

    /**
     * Deletes Kami rows explicitly so dry-run mode reports the same dependency
     * chain even on schemas where the database would cascade the delete.
     *
     * @param array<int, int> $fileIds
     * @return int
     * @throws \PDOException
     */
    protected function deleteKamiDocuments(array $fileIds): int
    {
        return $this->deleteRowsByStudentSubmissionFileId(self::TABLE_NAME_KAMI_DOCUMENT, $fileIds);
    }

    /**
     * Deletes submission file rows after integration records have been removed,
     * leaving storage hash cleanup to the existing garbage collector.
     *
     * @param array<int, int> $fileIds
     * @return int
     * @throws \PDOException
     */
    protected function deleteSubmissionFiles(array $fileIds): int
    {
        if ($fileIds === []) {
            return 0;
        }

        $placeholders = Builder::getArrayPlaceholders($fileIds, 'student_submission_file_id');
        $statement = $this->getPreparedStatement(
            $this->deleteStatement(self::TABLE_NAME_SUBMISSION_FILE) . " FROM " . self::TABLE_NAME_SUBMISSION_FILE . " WHERE id IN {$placeholders}"
        );
        $statement->execute(Builder::getArrayParameters($fileIds, 'student_submission_file_id'));

        return $statement->rowCount();
    }

    /**
     * Deletes parent submissions last so child-table constraints have already
     * been cleared for the current chunk.
     *
     * @param array<int, int> $submissionIds
     * @return int
     * @throws \PDOException
     */
    protected function deleteSubmissions(array $submissionIds): int
    {
        if ($submissionIds === []) {
            return 0;
        }

        $placeholders = Builder::getArrayPlaceholders($submissionIds, 'student_submission_id');
        $statement = $this->getPreparedStatement(
            $this->deleteStatement(self::TABLE_NAME_SUBMISSION) . " FROM " . self::TABLE_NAME_SUBMISSION . " WHERE id IN {$placeholders}"
        );
        $statement->execute(Builder::getArrayParameters($submissionIds, 'student_submission_id'));

        return $statement->rowCount();
    }

    /**
     * Shares the delete pattern for integration tables that reference
     * student_submission_file.id through student_submission_file_id.
     *
     * @param string $table
     * @param array<int, int> $fileIds
     * @return int
     * @throws \PDOException
     */
    private function deleteRowsByStudentSubmissionFileId(string $table, array $fileIds): int
    {
        if ($fileIds === []) {
            return 0;
        }

        $placeholders = Builder::getArrayPlaceholders($fileIds, 'student_submission_file_id');
        $statement = $this->getPreparedStatement(
            $this->deleteStatement($table) . " FROM {$table} WHERE student_submission_file_id IN {$placeholders}"
        );
        $statement->execute(Builder::getArrayParameters($fileIds, 'student_submission_file_id'));

        return $statement->rowCount();
    }
}
