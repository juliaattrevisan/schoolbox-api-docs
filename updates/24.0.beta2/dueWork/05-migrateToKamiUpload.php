#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Migrate Kami document references in `student_submission_file_kami` to
 * `kami_upload`.
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        $insertIntoKamiDocument = $this->getDatabaseInternal()->prepareQuery('
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
        $setStudentSubmissionFileKamiUploadId = $this->getDatabaseInternal()->prepareQuery('
            UPDATE student_submission_file_kami
            SET kami_upload_id = :kamiUploadId
            WHERE id = :id
        ');
        $selectFromStudentSubmissionFile = $this->getDatabaseInternal()->executeQuery('
            SELECT * FROM student_submission_file_kami
        ');
        $this->getLogger()->info(
            sprintf('%d student_submission_file_kami rows found.', $selectFromStudentSubmissionFile->rowCount())
        );
        foreach ($selectFromStudentSubmissionFile as $row) {
            $insertIntoKamiDocument->execute([
                'kamiDocumentId' => $row['kami_document_id'],
                'status'         => $row['status'],
                'errorMessage'   => $row['error_message'],
                'kamiCreatedAt'  => $row['kami_created_at'],
                'createdAt'      => $row['created_at'],
                'updatedAt'      => $row['updated_at'],
                'deletedAt'      => $row['deleted_at'],
            ]);
            $setStudentSubmissionFileKamiUploadId->execute([
                'id'             => $row['id'],
                'kamiUploadId'   => $this->getDatabaseInternal()->lastInsertId(),
            ]);
        }
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
};

(new CliTool\Kernel($app))->runApp();
