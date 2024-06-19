#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Alaress\Database\Exception as DatabaseException;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Migrate Kami document references in `student_submission_file_kami` to
 * `kami_upload`.
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        $insertIntoKamiUpload = $this->getDatabaseInternal()->prepareQuery('
            INSERT INTO kami_upload (
                kami_document_id,
                status,
                error_message,
                kami_created_at,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :kamiDocumentId,
                :status,
                :errorMessage,
                :kamiCreatedAt,
                :createdAt,
                :updatedAt,
                :deletedAt
            )
        ');
        $selectExistingKamiUploadId = $this->getDatabaseInternal()->prepareQuery('
            SELECT id FROM kami_upload WHERE kami_document_id = :kamiDocumentId
        ');
        $setStudentSubmissionFileKamiUploadId = $this->getDatabaseInternal()->prepareQuery('
            UPDATE student_submission_file_kami
            SET kami_upload_id = :kamiUploadId
            WHERE id = :id
        ');
        $selectFromStudentSubmissionFile = $this->getDatabaseInternal()->executeQuery('
            SELECT * FROM student_submission_file_kami WHERE kami_upload_id IS NULL
        ');
        $this->getLogger()->info(
            sprintf('Migrating %d student_submission_file_kami rows to kami_upload.', $selectFromStudentSubmissionFile->rowCount())
        );
        foreach ($selectFromStudentSubmissionFile as $row) {
            try {
                $insertIntoKamiUpload->execute([
                    'kamiDocumentId' => $row['kami_document_id'],
                    'status'         => $row['status'],
                    'errorMessage'   => $row['error_message'],
                    'kamiCreatedAt'  => $row['kami_created_at'],
                    'createdAt'      => $row['created_at'],
                    'updatedAt'      => $row['updated_at'],
                    'deletedAt'      => $row['deleted_at'],
                ]);
                $kamiUploadId = $this->getDatabaseInternal()->lastInsertId();
            } catch (DatabaseException) {
                // This Kami document already exists: warn and use existing
                $this->getLogger()->warning(
                    sprintf('kami_upload with kami_document_id="%s" exists: reusing that ID', $row['kami_document_id'])
                );
                $selectExistingKamiUploadId->execute(['kamiDocumentId' => $row['kami_document_id']]);
                $kamiUploadId = $selectExistingKamiUploadId->fetchColumn();
            }
            $setStudentSubmissionFileKamiUploadId->execute([
                'id'             => $row['id'],
                'kamiUploadId'   => $kamiUploadId,
            ]);
        }
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
};

(new CliTool\Kernel(
    $app,
    [Schoolbox\Application\CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG],
    true,
))->runApp();
