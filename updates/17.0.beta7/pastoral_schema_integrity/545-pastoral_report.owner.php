<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

/**
 * Ensures that pastoral_report.owner_id is valid and not null by
 * linking to the 'alaress' user.
 */
use Psr\Log\LoggerInterface;
use Monolog\Logger;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Ensure `pastoral_report`.`owner_id` referential integrity', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);


/** @noinspection PhpUnusedParameterInspection */
$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {

    $db             = Database::getInstance();
    $errorsOccurred = false;
    $successCount   = 0;

    $log->info('Searching for pastoral reports without a valid owner');
    $selectReportsWithoutOwner = $db->prepare("
        SELECT r.id AS 'r.id', r.owner_id AS 'r.owner_id'
        FROM pastoral_report r
        LEFT JOIN user u ON r.owner_id = u.id
        WHERE u.id IS NULL;
    ");
    $selectReportsWithoutOwner->execute();

    if (!$selectReportsWithoutOwner->rowCount()) {
        $log->info('No pastoral reports without a valid owner. OK.');
    } else {
        $log->info(sprintf('%d pastoral reports found without a owner user.', $selectReportsWithoutOwner->rowCount()));

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $selectReportsWithoutOwner->fetch(PDO::FETCH_ASSOC)) {
            $log->debug(sprintf('PastoralReport (id: %d), User (id: %s)', $row['r.id'], $row['r.owner_id'] ?: "'null'"));
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
            $updatePastoralReportOwner = $db->prepare('
                UPDATE pastoral_report r
                LEFT JOIN user u ON r.owner_id = u.id
                SET r.owner_id = :user_id
                WHERE u.id IS NULL;
            ');
            $log->info("Assigning ownership to 'alaress' user...");

            try {
                $updatePastoralReportOwner->execute(['user_id' => $alaressUserId]);
                $log->info(sprintf("%d pastoral report owners set to 'alaress' (id: %d)",
                    $updatePastoralReportOwner->rowCount(), $alaressUserId));
                $successCount = $updatePastoralReportOwner->rowCount();
            } catch (PDOException $e) {
                $log->error(sprintf("Unable to set pastoral report owners to 'alaress' (id: %d)", $alaressUserId), [$e]);
                $log->error($e->getMessage());
                $errorsOccurred = true;
            }
        }
    }

    $level   = $errorsOccurred ? Logger::ERROR : Logger::INFO;
    $message = sprintf("%d pastoral report owners set to 'alaress'", $successCount);
    $log->log($level, $message);

    exit($errorsOccurred ? 1 : 0);
});

