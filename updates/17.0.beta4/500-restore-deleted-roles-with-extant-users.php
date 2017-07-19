<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

/**
 * Wrapped in a PHP script to provide log of actions
 */
use Psr\Log\LoggerInterface;
use Monolog\Logger;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Restore soft-deleted roles if role still has users', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);

/** @noinspection PhpUnusedParameterInspection */
$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {

    $db = Database::getInstance();

    // Select the roles so we can log any changes to inform the user
    $selectQuery = $db->prepare("
        SELECT
          count(u.id) AS 'count_users',
          g.id AS 'g.id',
          g.name AS 'g.name'
        FROM user u
        JOIN groups g ON u.group_id = g.id
        WHERE g.deleted_at IS NOT NULL
        AND u.deleted_at IS NULL
        GROUP BY g.id;
    ");

    // Restore the roles
    $updateQuery = $db->prepare('
        UPDATE groups g
        SET g.deleted_at = NULL
        WHERE g.id = :group_id;
    ');

    $successCount = 0;
    $warningCount = 0;
    $failureCount = 0;

    $log->info('Selecting all soft-deleted roles that have non-deleted users');
    $selectQuery->execute();

    if (!$selectQuery->rowCount()) {
        $log->info('No soft-deleted roles found with non-deleted users. OK.');
    } else {
        $log->info(sprintf('%d soft-deleted roles found with non-deleted users. Restoring roles...', $selectQuery->rowCount()));

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $selectQuery->fetch(PDO::FETCH_ASSOC)) {
            $countUsers = $row['count_users'];
            $groupId    = $row['g.id'];
            $groupName  = $row['g.name'];

            $log->info(sprintf("Group/Role (%s) '%s' is soft-deleted but has %s non-soft-deleted users", $groupId, $groupName, $countUsers));

            try {
                $updateQuery->execute([
                    'group_id' => $groupId,
                ]);
                $log->info(sprintf("Group/Role (%s) '%s' restored. %d rows affected", $groupId, $groupName, $updateQuery->rowCount()));
                $successCount++;
            } catch (PDOException $e) {
                $log->error(sprintf("Unable to restore Group/Role (%s) '%s'. %d rows affected", $groupId, $groupName, $updateQuery->rowCount()), [$e]);
                $log->warning($e->getMessage());
                $failureCount++;
            }
        }
    }

    $message = sprintf('%d queries executed (%d warnings), %d failures', $successCount, $warningCount, $failureCount);
    /** @noinspection NestedTernaryOperatorInspection */
    $level = $failureCount ? Logger::ERROR : ($warningCount ? Logger::WARNING : Logger::INFO);
    $log->log($level, $message);

    exit(($warningCount || $failureCount) ? 1 : 0);
});

