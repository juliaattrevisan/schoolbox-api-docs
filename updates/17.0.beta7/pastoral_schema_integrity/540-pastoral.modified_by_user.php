<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

/**
 * The pastoral.modified_by_user_id relationship currently lacks referential integrity.
 * It is optional, where 'null' represents 'imported from SIS'.
 *
 * This script searches for any records linked to missing users and [re]assigns modifiedBy to the 'alaress' user.
 */
use Psr\Log\LoggerInterface;
use Monolog\Logger;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Ensure `pastoral`.`modified_by_user_id` relationship integrity', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);


/** @noinspection PhpUnusedParameterInspection */
$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {

    $db             = Database::getInstance();
    $errorsOccurred = false;
    $successCount   = 0;

    $log->info('Searching for pastoral records with an invalid modifiedBy user');
    $selectRecordsWithoutModifier = $db->prepare("
        SELECT p.id AS 'p.id', p.modified_by_user_id AS 'p.modified_by_user_id'
        FROM pastoral p
        LEFT JOIN user u ON p.modified_by_user_id = u.id
        WHERE p.modified_by_user_id IS NOT NULL
        AND u.id IS NULL;
    ");
    $selectRecordsWithoutModifier->execute();

    if (!$selectRecordsWithoutModifier->rowCount()) {
        $log->info('No pastoral records found with an invalid modifier. OK.');
    } else {
        $log->info(sprintf('%d pastoral records found with an invalid modifier.',
            $selectRecordsWithoutModifier->rowCount()));

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $selectRecordsWithoutModifier->fetch(PDO::FETCH_ASSOC)) {
            $log->debug(sprintf('Pastoral (id: %d), ModifiedBy User (id: %s)',
                $row['p.id'], $row['p.modified_by_user_id']));
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
            $log->error("No 'alaress' user found. Cannot re-assign pastoral modifier.");
            $errorsOccurred = true;
        } elseif ($selectAlaressUser->rowCount() >= 2) {
            $log->error("Ambiguous state. Multiple 'alaress' users found. Cannot re-assign pastoral modifier.");
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
            $updatePastoralModifierQuery = $db->prepare('
                UPDATE pastoral p
                LEFT JOIN user u ON p.modified_by_user_id = u.id
                SET p.modified_by_user_id = :user_id
                WHERE u.id IS NULL;
            ');
            $log->info("Assigning modifiedBy to 'alaress' user...");

            try {
                $updatePastoralModifierQuery->execute(['user_id' => $alaressUserId]);
                $log->info(sprintf("%d pastoral record modifiedBy users set to 'alaress' (id: %d)",
                    $updatePastoralModifierQuery->rowCount(), $alaressUserId));
                $successCount = $updatePastoralModifierQuery->rowCount();
            } catch (PDOException $e) {
                $log->error(sprintf("Unable to set record modifiedBy user to 'alaress' (id: %d)", $alaressUserId), [$e]);
                $log->error($e->getMessage());
                $errorsOccurred = true;
            }
        }
    }

    $level   = $errorsOccurred ? Logger::ERROR : Logger::INFO;
    $message = sprintf("%s pastoral record modifiedBy reassigned to 'alaress' user", $successCount);
    $log->log($level, $message);

    exit($errorsOccurred ? 1 : 0);
});

