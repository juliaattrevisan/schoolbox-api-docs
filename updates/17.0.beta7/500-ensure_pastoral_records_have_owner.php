<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

/**
 * The pastoral.owner_id relationship is currently nullable to account for hard-deleted users.
 * This causes problems in the code as it assumes the owner always exists.
 *
 * This script searches for any records linked to missing users, or with no owner
 * specified at all and [re]assigns ownership to the 'alaress' user.
 */
use Psr\Log\LoggerInterface;
use Monolog\Logger;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Ensure Pastoral Owner relationship integrity', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);


/** @noinspection PhpUnusedParameterInspection */
$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {

    $db             = Database::getInstance();
    $errorsOccurred = false;
    $successCount   = 0;

    $log->info('Searching for pastoral records without a valid owner');
    $selectRecordsWithoutOwners = $db->prepare("
        SELECT p.id AS 'p.id', p.owner_id AS 'p.owner_id'
        FROM pastoral p
        LEFT JOIN user u ON p.owner_id = u.id
        WHERE u.id IS NULL;        
    ");
    $selectRecordsWithoutOwners->execute();

    if (!$selectRecordsWithoutOwners->rowCount()) {
        $log->info('No pastoral records found without a valid owner. OK.');
    } else {
        $log->info(sprintf('%d pastoral records found without a valid owner.', $selectRecordsWithoutOwners->rowCount()));

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $selectRecordsWithoutOwners->fetch(PDO::FETCH_ASSOC)) {
            $log->debug('Pastoral (id: %d), Owner (id: %s)', $row['p.id'], $row['p.owner_id'] ?: "'null'");
        }

        // Select the Alaress User
        $log->info("Searching for the 'alaress' user...");
        $alaressUserId     = null;
        $selectAlaressUser = $db->prepare('
            SELECT u.id, u.username, u.firstname, u.lastname
            FROM user u
            WHERE u.username LIKE :username
        ');
        $selectAlaressUser->execute(['username' => 'alaress']);

        if (0 === $selectAlaressUser->rowCount()) {
            $log->error("No 'alaress' user found. Cannot re-assign pastoral ownership.");
            $errorsOccurred = true;
        } elseif ($selectAlaressUser->rowCount() >= 2) {
            $log->error("Ambiguous state. Multiple 'alaress' users found. Cannot re-assign pastoral ownership.");
            $errorsOccurred = true;
        } else {
            $row = $selectAlaressUser->fetch(PDO::FETCH_ASSOC);
            $log->info(sprintf("'alaress' user found. (id: '%d', username: '%s', first: '%s', last: '%s')",
                $row['id'], $row['username'], $row['firstname'], $row['lastname']
            ));
            $alaressUserId = $row['id'];
        }
        $selectAlaressUser->closeCursor();

        if ($alaressUserId) {
            $updatePastoralOwnerQuery = $db->prepare('
                UPDATE pastoral p
                LEFT JOIN user u ON p.owner_id = u.id
                SET p.owner_id = :user_id
                WHERE u.id IS NULL;
            ');
            $log->info("Assigning ownership to 'alaress' user...");

            try {
                $updatePastoralOwnerQuery->execute(['user_id' => $alaressUserId]);
                $log->info(sprintf("%d pastoral record owners set to 'alaress' (id: %d)", $updatePastoralOwnerQuery->rowCount(), $alaressUserId));
                $successCount = $updatePastoralOwnerQuery->rowCount();
            } catch (PDOException $e) {
                $log->error(sprintf("Unable to set record owners to 'alaress' (id: %d)", $alaressUserId), [$e]);
                $log->error($e->getMessage());
                $errorsOccurred = true;
            }
        }
    }

    $level   = $errorsOccurred ? Logger::ERROR : Logger::INFO;
    $message = sprintf("%s pastoral record owners reassigned to 'alaress' user", $successCount);
    $log->log($level, $message);

    exit($errorsOccurred ? 1 : 0);
});

