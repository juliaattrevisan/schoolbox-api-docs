<?php
/**
 * After adding new columns in preceding SQL file, this script populates those fields
 *
 * `student_submission`.`updated_at`      FROM `student_submission`.`submitted`
 * `student_submission`.`deleted_at`      FROM `assessment`.`deleted_at`
 *
 * `student_submission_file`.`deleted_at` FROM `assessment`.`deleted_at`
 *
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;
use Schoolbox\Mapper\Exception as MapperException;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Populate DueWork Submission TimeStamps', [
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
]);

$kernel->run(function($di, $loop, LoggerInterface $log, $opts) {

    $db = Database::getInstance();

    // Update StudentSubmission
    $updateStudentSubmissionUpdatedAtQuery = $db->prepare("
        UPDATE `student_submission`
        SET 
          `updated_at` = :updated
        WHERE
          `id` = :id
    ");

    $updateStudentSubmissionDeletedAtQuery = $db->prepare("
        UPDATE `student_submission`
        SET 
          `deleted_at` = :deleted
        WHERE
          `id` = :id
    ");

    // Update StudentSubmissionFile
    $updateStudentSubmissionFileQuery = $db->prepare("
        UPDATE `student_submission_file`
        SET 
          `deleted_at` = :deleted
        WHERE
          `id` = :id
    ");

    $selectStudentSubmissionQuery = $db->prepare("
        SELECT
            `ss`.`id`        AS 'ss.id',
            `ss`.`submitted` AS 'ss.submitted',
            `a`.`id`         AS 'a.id',
            `a`.`title`      AS 'a.title',
            `a`.`deleted_at` AS 'a.deleted_at'
        FROM `student_submission` `ss`
        JOIN `submission_box` `sb` ON `ss`.`box_id` =  `sb`.`id`
        JOIN `assessment` `a` ON `sb`.`assessment_id` = `a`.`id`
    ");

    $selectStudentSubmissionFileQuery = $db->prepare("
        SELECT
            `ssf`.`id`       AS 'ssf.id',
            `a`.`deleted_at` AS 'a.deleted_at'
        FROM `student_submission` `ss`
        JOIN `submission_box` `sb` ON `ss`.`box_id` =  `sb`.`id`
        JOIN `assessment` `a` ON `sb`.`assessment_id` = `a`.`id`
        JOIN `student_submission_file` `ssf` ON `ss`.`id` = `ssf`.`student_submission_id`
        WHERE a.deleted_at IS NOT NULL
    ");


    // Go
    $selectStudentSubmissionQuery->execute();
    $log->debug(sprintf('%d student submissions found', $selectStudentSubmissionQuery->rowCount()));
    $successCount_1 = 0;
    $failureCount_1 = 0;

    $rows = $selectStudentSubmissionQuery->fetchAll(PDO::FETCH_ASSOC);

    // #1 Set the 'updated_at' to match the 'submitted' date
    foreach ($rows as $row) {
        $studentSubmission_Id        = $row['ss.id'];
        $studentSubmission_createdAt = $row['ss.submitted'];

        $log->info(sprintf("Setting Submission 'updated_at' = '%s' (id: %d)...", $studentSubmission_createdAt, $studentSubmission_Id));

        // Set 'updated_at' field value
        try {
            $db->beginTransaction();

            // Create assessment
            $updateStudentSubmissionUpdatedAtQuery->execute([
                'updated' => $studentSubmission_createdAt,
                'id'      => intval($studentSubmission_Id),
            ]);
            $log->debug("Set `student_submission`.`updated_at`");

            $successCount_1++;
            $db->commit();

        } catch (PDOException $e) {
            $log->error(sprintf("StudentSubmission (id: %d) could not set `updated_at` field!", $studentSubmission_Id), [$e]);
            $log->warning($e->getMessage());
            $failureCount_1++;
            $db->rollBack();
        }
    }
    $log->info(sprintf("%d StudentSubmission `updated_at` fields set, %d failures\n", $successCount_1, $failureCount_1));


    // #2 Set the StudentSubmission 'deleted_at' to match the Assessment 'deleted_at'
    $log->debug(sprintf('%d student submissions found', $selectStudentSubmissionQuery->rowCount()));
    $successCount_2 = 0;
    $failureCount_2 = 0;
    foreach ($rows as $row) {
        $studentSubmission_Id        = $row['ss.id'];
        $assessment_id               = $row['a.id'];
        $assessment_title            = $row['a.title'];
        $assessment_deletedAt        = $row['a.deleted_at'];

        // Ignore non-deleted Assessments
        if($assessment_deletedAt){

            $log->info(sprintf("Assessment '%s' (id: %d) is deleted. Soft-deleting Submission (id: %d)...",
                $assessment_title, $assessment_id, $studentSubmission_Id));

            // Soft-Delete this StudentSubmission
            try {
                $db->beginTransaction();

                // Create assessment
                $updateStudentSubmissionDeletedAtQuery->execute([
                    'deleted' => $assessment_deletedAt,
                    'id'      => intval($studentSubmission_Id),
                ]);
                $log->debug("Set `student_submission`.`deleted_at`");

                $successCount_2++;
                $db->commit();

            } catch (PDOException $e) {
                $log->error(sprintf("StudentSubmission (id: %d) could not set `deleted_at` field!", $studentSubmission_Id), [$e]);
                $log->warning($e->getMessage());
                $failureCount_2++;
                $db->rollBack();
            }
        }

    }
    $log->info(sprintf("%d StudentSubmission `deleted_at` fields set, %d failures\n", $successCount_2, $failureCount_2));


    // #3 Set the StudentSubmissionFile `deleted_at` to match Assessment `deleted_at`
    $selectStudentSubmissionFileQuery->execute();
    $log->debug(sprintf('%d student submission files found for deleted assessments', $selectStudentSubmissionFileQuery->rowCount()));
    $successCount_3 = 0;
    $failureCount_3 = 0;

    // Set the 'updated_at' to match the 'submitted' date
    $rows = $selectStudentSubmissionFileQuery->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $studentSubmissionFile_Id    = $row['ssf.id'];
        $assessment_deletedAt        = $row['a.deleted_at'];

        $log->info(sprintf("Setting Submission File 'deleted_at' = '%s' (id: %d)...", $assessment_deletedAt, $studentSubmissionFile_Id));

        // Set 'updated_at' field value
        try {
            $db->beginTransaction();

            // Create assessment
            $updateStudentSubmissionFileQuery->execute([
                'deleted' => $assessment_deletedAt,
                'id'      => intval($studentSubmissionFile_Id),
            ]);
            $log->debug("Set `student_submission_file`.`deleted_at`");

            $successCount_3++;
            $db->commit();

        } catch (PDOException $e) {
            $log->error(sprintf("StudentSubmissionFile (id: %d) could not set `deleted_at` field!", $studentSubmissionFile_Id), [$e]);
            $log->warning($e->getMessage());
            $failureCount_3++;
            $db->rollBack();
        }
    }
    $log->info(sprintf('%d StudentSubmissionFile `deleted_at` fields set, %d failures', $successCount_3, $failureCount_3));

    $failureCount = $failureCount_1 + $failureCount_2 + $failureCount_3;

    exit($failureCount > 0 ? 1 : 0);
});
