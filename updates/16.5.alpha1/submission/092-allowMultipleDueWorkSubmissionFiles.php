<?php
/**
 * After adding new columns to 'student_submission' in preceding SQL file, this script populates those fields
 *
 * `created_at`      FROM `submitted_at`
 * `updated_at`      FROM `submitted_at`
 * `submitted_at`    FROM `submitted_at` & !`draft`
 * `deleted_at`      FROM `assessment`.`deleted_at`
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
          `created_at` = :created,
          `updated_at` = :updated,
          `submitted_at` = :submitted,
          `deleted_at` = :deleted
        WHERE
          `id` = :id
    ");


    $selectStudentSubmissionQuery = $db->prepare("
        SELECT
            `ss`.`id`           AS 'ss.id',
            `ss`.`draft`        AS 'ss.draft',
            `ss`.`submitted_at` AS 'ss.submitted_at',
            `a`.`id`            AS 'a.id',
            `a`.`title`         AS 'a.title',
            `a`.`deleted_at`    AS 'a.deleted_at'
        FROM `student_submission` `ss`
        JOIN `submission_box` `sb` ON `ss`.`box_id` =  `sb`.`id`
        JOIN `assessment` `a` ON `sb`.`assessment_id` = `a`.`id`
    ");


    // Go
    $selectStudentSubmissionQuery->execute();
    $log->debug(sprintf('%d student submissions found', $selectStudentSubmissionQuery->rowCount()));
    $successCount = 0;
    $failureCount= 0;


    foreach ($selectStudentSubmissionQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $submissionId          = $row['ss.id'];
        $submissionIsDraft     = $row['ss.draft'];
        $submissionSubmittedAt = $row['ss.submitted_at'];
        $assessmentDeletedAt   = $row['a.deleted_at'];

        $log->info(sprintf("Updating Submission (id: %d)...", $submissionId));

        // Set 'updated_at' field value
        try {
            $db->beginTransaction();

            // Create assessment
            $updateStudentSubmissionUpdatedAtQuery->execute([
                'created'   => $submissionSubmittedAt,
                'updated'   => $submissionSubmittedAt,
                'submitted' => $submissionIsDraft ? null : $submissionSubmittedAt,
                'deleted'   => $assessmentDeletedAt,
                'id'        => intval($submissionId),
            ]);
            $log->debug("Updated Submission.");

            $successCount++;
            $db->commit();

        } catch (PDOException $e) {
            $log->error(sprintf("Could not update studentSubmission (id: %d)!", $submissionId), [$e]);
            $log->warning($e->getMessage());
            $failureCount++;
            $db->rollBack();
        }
    }
    $log->info(sprintf("%d StudentSubmissions updated, %d failures\n", $successCount, $failureCount));


    exit($failureCount > 0 ? 1 : 0);
});
