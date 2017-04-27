<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;
use Schoolbox\Mapper\Exception as MapperException;
use Monolog\Logger;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Soft-Delete Confirmation of Submissions', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);

/**
 * Soft-Deletes all 'Confirmation of Submission' submission returns.
 * Wrapped in a PHP script to insert a little extra log info
 */
$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {

    $db = Database::getInstance();

    $now = Alaress_Date_Format::now();

    // Soft-delete all non-deleted confirmations of submission. This concept is determined dynamically in 16.5+
    $updateQuery = $db->prepare('
        UPDATE submission_return
        SET deleted_at = :deleted_at
        WHERE is_confirmation_of_submission IS TRUE
        AND deleted_at IS NULL;
    ');

    $successCount = 0;
    $warningCount = 0;
    $failureCount = 0;

    $log->info(sprintf("Attempting to update confirmation of submissions to soft-deleted at '%s'", $now->getDatabase()));

    try {
        $updateQuery->execute(['deleted_at' => $now->getDatabase()]);
        $log->info(sprintf("%d rows soft-deleted at '%s'", $updateQuery->rowCount(), $now->getDatabase()));
        $successCount++;
    } catch (PDOException $e) {
        $log->error(sprintf('Unable to soft-delete confirmation of submissions'), [$e]);
        $log->warning($e->getMessage());
        $failureCount++;
    }


    $message = sprintf('%d queries executed (%d warnings), %d failures', $successCount, $warningCount, $failureCount);
    $level   = $failureCount ? Logger::ERROR : ($warningCount ? Logger::WARNING : Logger::INFO);
    $log->log($level, $message);

    exit(($warningCount || $failureCount) ? 1 : 0);
});

