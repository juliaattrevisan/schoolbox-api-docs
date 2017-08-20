<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

/**
 * The `pastoral_access` table appears to contain duplicate records in live data sets.
 *
 * The script hard-deletes all but the first row in duplicate sets.
 */
use Psr\Log\LoggerInterface;
use Monolog\Logger;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Hard-delete `pastoral_access` duplicate rows', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);


/** @noinspection PhpUnusedParameterInspection */
$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {

    $db           = Database::getInstance();
    $countSuccess = 0;
    $countFailure = 0;

    // Duplicate 'individual' access records
    $selectDuplicateUserAccess = $db->prepare('
        SELECT GROUP_CONCAT(id) AS ids, pastoral_id, user_id, count(*) AS count
        FROM pastoral_access
        WHERE deleted_at IS NULL
        AND user_id IS NOT NULL
        GROUP BY pastoral_id, user_id
        HAVING count > 1
        ORDER BY pastoral_id, user_id;
    ');

    // Duplicate 'role' access records
    $selectDuplicateRoleAccess = $db->prepare('
        SELECT GROUP_CONCAT(id) AS ids, pastoral_id, role_id, count(*) AS count
        FROM pastoral_access
        WHERE deleted_at IS NULL
        AND role_id IS NOT NULL
        GROUP BY pastoral_id, role_id
        HAVING count > 1
        ORDER BY pastoral_id, role_id;
    ');

    // Duplicate 'group' access records
    $selectDuplicateGroupAccess = $db->prepare('
        SELECT GROUP_CONCAT(id) AS ids, pastoral_id, group_id, count(*) AS count
        FROM pastoral_access
        WHERE deleted_at IS NULL
        AND group_id IS NOT NULL
        GROUP BY pastoral_id, group_id
        HAVING count > 1
        ORDER BY pastoral_id, group_id;
    ');

    $deleteDuplicateUserAccess = $db->prepare('
        DELETE FROM pastoral_access
        WHERE id = :id;
    ');

    // Individual Access
    $log->info("Fetching duplicate 'individual' access records...");
    $selectDuplicateUserAccess->execute();
    if (!$selectDuplicateUserAccess->rowCount()) {
        $log->info("No duplicate 'individual' access records found. OK.");
    } else {
        $log->info(sprintf("%d sets of duplicate 'individual' access records found",
            $selectDuplicateUserAccess->rowCount()));

        foreach ($selectDuplicateUserAccess->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $log->debug(sprintf("ids: '%s', pastoral_id: %d, user_id: %d, count: %d",
                $row['ids'], $row['pastoral_id'], $row['user_id'], $row['count']));

            $first = true;
            foreach (explode(',', $row['ids']) as $id) {
                if ($first) {
                    $log->debug(sprintf('Retaining first in set (id: %d)', $id));
                } else {
                    $log->info(sprintf('Deleting duplicate in set (id: %d)', $id));
                    try {
                        $deleteDuplicateUserAccess->execute(['id' => $id]);
                        $countSuccess++;
                    } catch (PDOException $e) {
                        $log->error(sprintf('Failed to delete row (id: %d)', $id), [$e]);
                        $log->error($e->getMessage());
                        $countFailure++;
                    }
                }
                $first = false;
            }
        }
    }


    // Role Access
    $log->info("Fetching duplicate 'role' access records...");
    $selectDuplicateRoleAccess->execute();
    if (!$selectDuplicateRoleAccess->rowCount()) {
        $log->info("No duplicate 'role' access records found. OK.");
    } else {
        $log->info(sprintf("%d sets of duplicate 'role' access records found",
            $selectDuplicateRoleAccess->rowCount()));

        foreach ($selectDuplicateRoleAccess->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $log->debug(sprintf("ids: '%s', pastoral_id: %d, role_id: %d, count: %d",
                $row['ids'], $row['pastoral_id'], $row['role_id'], $row['count']));

            $first = true;
            foreach (explode(',', $row['ids']) as $id) {
                if ($first) {
                    $log->debug(sprintf('Retaining first in set (id: %d)', $id));
                } else {
                    $log->info(sprintf('Deleting duplicate in set (id: %d)', $id));
                    try {
                        $deleteDuplicateUserAccess->execute(['id' => $id]);
                        $countSuccess++;
                    } catch (PDOException $e) {
                        $log->error(sprintf('Failed to delete row (id: %d)', $id), [$e]);
                        $log->error($e->getMessage());
                        $countFailure++;
                    }
                }
                $first = false;
            }
        }
    }


    // Group Access
    $log->info("Fetching duplicate 'group' access records...");
    $selectDuplicateGroupAccess->execute();
    if (!$selectDuplicateGroupAccess->rowCount()) {
        $log->info("No duplicate 'group' access records found. OK.");
    } else {
        $log->info(sprintf("%d sets of duplicate 'group' access records found",
            $selectDuplicateGroupAccess->rowCount()));

        foreach ($selectDuplicateGroupAccess->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $log->debug(sprintf("ids: '%s', pastoral_id: %d, group_id: %d, count: %d",
                $row['ids'], $row['pastoral_id'], $row['group_id'], $row['count']));

            $first = true;
            foreach (explode(',', $row['ids']) as $id) {
                if ($first) {
                    $log->debug(sprintf('Retaining first in set (id: %d)', $id));
                } else {
                    $log->info(sprintf('Deleting duplicate in set (id: %d)', $id));
                    try {
                        $deleteDuplicateUserAccess->execute(['id' => $id]);
                        $countSuccess++;
                    } catch (PDOException $e) {
                        $log->error(sprintf('Failed to delete row (id: %d)', $id), [$e]);
                        $log->error($e->getMessage());
                        $countFailure++;
                    }
                }
                $first = false;
            }
        }
    }

    $level   = $countFailure ? Logger::ERROR : Logger::INFO;
    $message = sprintf('%d duplicate pastoral access successfully deleted, %d failures', $countSuccess, $countFailure);
    $log->log($level, $message);

    exit($countFailure ? 1 : 0);
});

