<?php
/**
 * After adding new columns in preceding SQL file, this script populates those fields
 *
 * `published_at`    FROM `created_at` & `submission_box`.`mark_visibility` = 1
 * `updated_at`      FROM `created_at`
 *
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\Learning\Assessment\AbstractAssessment;


require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Populate DueWork Submission TimeStamps', [
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
]);

$kernel->run(function($di, $loop, LoggerInterface $log, $opts) {

  $db = Database::getInstance();

  // Update StudentSubmission
  $updateSubmissionReturnQuery = $db->prepare("
        UPDATE `submission_return`
        SET 
          `published_at` = :published,
          `updated_at` = :updated
        WHERE
          `id` = :id
    ");


  $selectSubmissionReturnQuery = $db->prepare("
        SELECT
            `sr`.`id`              AS 'sr.id',
            `sr`.`created_at`      AS 'sr.created_at',
            `sb`.`mark_visibility` AS 'sb.mark_visibility'
        FROM `submission_return` `sr`
        JOIN `submission_box` `sb` ON `sr`.`box_id` =  `sb`.`id`
    ");


  // Go
  $selectSubmissionReturnQuery->execute();
  $log->debug(sprintf('%d submission returns found', $selectSubmissionReturnQuery->rowCount()));
  $successCount = 0;
  $failureCount= 0;


  foreach ($selectSubmissionReturnQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $returnId         = $row['sr.id'];
    $returnCreatedAt  = $row['sr.created_at'];
    $boxVisibility    = $row['sb.mark_visibility'];

    $log->info(sprintf("Updating Return (id: %d)...", $returnId));

    // Set 'updated_at' field value
    try {
      $db->beginTransaction();

      // Create assessment
      $updateSubmissionReturnQuery->execute([
          'published' => MapperException::MARK_VISIBILITY_PRIVATE == $boxVisibility ? null : $returnCreatedAt,
          'updated'   => $returnCreatedAt,
          'id'        => intval($returnId),
      ]);
      $log->debug("Updated Return.");

      $successCount++;
      $db->commit();

    } catch (PDOException $e) {
      $log->error(sprintf("Could not update Return (id: %d)!", $returnId), [$e]);
      $log->warning($e->getMessage());
      $failureCount++;
      $db->rollBack();
    }
  }
  $log->info(sprintf("%d StudentSubmissions updated, %d failures\n", $successCount, $failureCount));


  exit($failureCount > 0 ? 1 : 0);
});
