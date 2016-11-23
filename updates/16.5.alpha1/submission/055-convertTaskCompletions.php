<?php
/**
 * Convert Task Completions from old format to new format
 *
 * For now, technically 'updated_at' and 'deleted_at' are redundant as we cannot update or delete records.
 * But this may change in the future and it makes the records consistent with the common architecture.
 *
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
use Psr\Log\LoggerInterface;
use Schoolbox\Mapper\Exception as MapperException;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Convert Quiz and Due Work', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);

$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {

    $db = Database::getInstance();

    // Select quiz/duework queries
    $selectTaskCompletionQuery = $db->prepare("SELECT `id`, `created_at` FROM  `task_instance_completion`");
    $updateTaskCompletionQuery = $db->prepare("UPDATE `task_instance_completion` SET `updated_at` = :updated WHERE `id` = :id");

    // Go
    $selectTaskCompletionQuery->execute();
    $log->debug(sprintf('%d TaskCompletions found', $selectTaskCompletionQuery->rowCount()));
    $successCount = 0;
    $failureCount = 0;
    foreach ($selectTaskCompletionQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id        = $row['id'];
        $created   = $row['created_at'];

        // Insert 'updated_at' value
        try {
            $db->beginTransaction();

            // Create assessment
            $updateTaskCompletionQuery->execute([
                'id'      => $id,
                'updated' => $created,
            ]);
            $log->debug(sprintf('Updated TaskCompletion (id: %d)', $id));
            $successCount++;
            $db->commit();

        } catch (PDOException $e) {
            $log->error(sprintf('TaskCompletion (id: %d) could not be updated!', $id, [$e]));
            $log->warning($e->getMessage());
            $failureCount++;
            $db->rollBack();
        }
    }

    $log->info(sprintf('%d TaskCompletions updated, %d failures', $successCount, $failureCount));
    exit($failureCount > 0 ? 1 : 0);
});
