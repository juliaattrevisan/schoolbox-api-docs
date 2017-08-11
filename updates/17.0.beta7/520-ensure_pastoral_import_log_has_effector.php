<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

/**
 * Ensures that pastoral_import_log.user_id (ie effector) is valid and not null by
 * linking to the 'alaress' user.
 */
use Psr\Log\LoggerInterface;
use Monolog\Logger;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Ensure PastoralImportLog.UserId referential integrity', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);


/** @noinspection PhpUnusedParameterInspection */
$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {

    $db             = Database::getInstance();
    $errorsOccurred = false;
    $successCount   = 0;

    $log->info('Searching for pastoral imports without a valid user');
    $selectLogsWithoutUser = $db->prepare("
        SELECT l.id AS 'l.id', l.user_id AS 'l.user_id'
        FROM pastoral_import_log l
        LEFT JOIN user u ON l.user_id = u.id
        WHERE u.id IS NULL;
    ");
    $selectLogsWithoutUser->execute();

    if (!$selectLogsWithoutUser->rowCount()) {
        $log->info('No pastoral import logs without a valid user. OK.');
    } else {
        $log->info(sprintf('%d pastoral import logs found without a valid user.', $selectLogsWithoutUser->rowCount()));

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $selectLogsWithoutUser->fetch(PDO::FETCH_ASSOC)) {
            $log->debug('PastoralImportLog (id: %d), User (id: %s)', $row['l.id'], $row['l.user_id'] ?: "'null'");
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
            $log->error("No 'alaress' user found. Cannot re-assign pastoral import log user.");
            $errorsOccurred = true;
        } elseif ($selectAlaressUser->rowCount() >= 2) {
            $log->error("Ambiguous state. Multiple 'alaress' users found. Cannot re-assign pastoral import log user.");
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
            $updatePastoralImportLogUser = $db->prepare('
                UPDATE pastoral_import_log l
                LEFT JOIN user u ON l.user_id = u.id
                SET l.user_id = :user_id
                WHERE u.id IS NULL;
            ');
            $log->info("Assigning ownership to 'alaress' user...");

            try {
                $updatePastoralImportLogUser->execute(['user_id' => $alaressUserId]);
                $log->info(sprintf("%d pastoral import log users set to 'alaress' (id: %d)",
                    $updatePastoralImportLogUser->rowCount(), $alaressUserId));
                $successCount = $updatePastoralImportLogUser->rowCount();
            } catch (PDOException $e) {
                $log->error(sprintf("Unable to set pastoral import log users to 'alaress' (id: %d)", $alaressUserId), [$e]);
                $log->error($e->getMessage());
                $errorsOccurred = true;
            }
        }
    }

    $level   = $errorsOccurred ? Logger::ERROR : Logger::INFO;
    $message = sprintf("%d pastoral import log users set to 'alaress'", $successCount);
    $log->log($level, $message);

    exit($errorsOccurred ? 1 : 0);
});

