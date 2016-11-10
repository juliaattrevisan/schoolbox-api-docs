<?php
/**
 * In the past, 'plagscan_document'.'file_id' linked to 'student_submission'.'id'
 * It now needs to link to 'student_submission_file'.'id'
 *
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;
use Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\Assessment\Component\SubmissionBox;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Populate DueWork Submission TimeStamps', [
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
]);

$kernel->run(function($di, $loop, LoggerInterface $log, $opts) {

    $db = Database::getInstance();

    // Update StudentSubmission
    $updatePlagscanQuery = $db->prepare("
        UPDATE `plagscan_document`
        SET 
          `student_submission_file_id` = :studentSubmissionFile
        WHERE
          `id` = :id
    ");


    $selectQuery = $db->prepare("
        SELECT
            `ssf`.`id`             AS 'ssf.id',
            `p`.`id`               AS 'p.id'
        FROM `plagscan_document` `p`           
        LEFT JOIN `student_submission` `ss` ON `ss`.`id` = `p`.`student_submission_file_id`  
        JOIN `student_submission_file` `ssf` ON `ss`.`id` =  `ssf`.`student_submission_id`;
    ");


    // Go
    $selectQuery->execute();
    $log->debug(sprintf('%d Plagscan documents found', $selectQuery->rowCount()));
    $successCount = 0;
    $failureCount= 0;


    foreach ($selectQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $plagscanId       = $row['p.id'];
        $submissionFileId = $row['ssf.id'];

        $log->info(sprintf("Updating Plagscan (id: %d)...", $plagscanId));

        try {
            $db->beginTransaction();

            // Create assessment
            $updatePlagscanQuery->execute([
                'studentSubmissionFile' => $submissionFileId,
                'id'                    => intval($plagscanId),
            ]);
            $log->debug("Updated Plagscan.");

            $successCount++;
            $db->commit();

        } catch (PDOException $e) {
            $log->error(sprintf("Could not update Plagscan (id: %d)!", $plagscanId), [$e]);
            $log->warning($e->getMessage());
            $failureCount++;
            $db->rollBack();
        }
    }
    $log->info(sprintf("%d Plagscan updated, %d failures\n", $successCount, $failureCount));


    exit($failureCount > 0 ? 1 : 0);
});
