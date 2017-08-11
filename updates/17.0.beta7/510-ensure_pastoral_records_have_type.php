<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

/**
 * Ensures that pastoral.type_id is valid and not null by inserting a new 'Unknown Type' type and
 * linking invalid pastoral records to it.
 */
use Psr\Log\LoggerInterface;
use Monolog\Logger;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Ensure Pastoral Type referential integrity', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);


/** @noinspection PhpUnusedParameterInspection */
$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {

    $db             = Database::getInstance();
    $errorsOccurred = false;
    $successCount   = 0;

    $log->info('Searching for pastoral records without a valid type');
    $selectRecordsWithoutType = $db->prepare("
        SELECT p.id AS 'p.id', p.type_id AS 'p.type_id'
        FROM pastoral p
        LEFT JOIN lu_pastoral_type t ON p.type_id = t.id
        WHERE t.id IS NULL;        
    ");
    $selectRecordsWithoutType->execute();

    if (!$selectRecordsWithoutType->rowCount()) {
        $log->info('No pastoral records found without a valid type. OK.');
    } else {
        $log->info(sprintf('%d pastoral records found without a valid type.', $selectRecordsWithoutType->rowCount()));

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $selectRecordsWithoutType->fetch(PDO::FETCH_ASSOC)) {
            $log->debug('Pastoral (id: %d), Type (id: %s)', $row['p.id'], $row['p.type_id'] ?: "'null'");
        }

        // Create a new PastoralType
        $insertTypeQuery = $db->prepare('
            INSERT INTO lu_pastoral_type (`name`, `external_code`, `deleted_at`) VALUES (:name, NULL, NULL);
        ');
        $log->info("Inserting 'Unknown Type' type record...");

        $unknownTypeId = null;
        try {
            $insertTypeQuery->execute(['name' => 'Unknown Type']);
            $unknownTypeId = $db->lastInsertId();
            $log->info(sprintf("'Unknown Type' successfully inserted (id: %d)", $unknownTypeId));
        } catch (PDOException $e) {
            $log->error("Unable to insert 'Unknown Type'", [$e]);
            $log->error($e->getMessage());
            $errorsOccurred = true;
        }

        if ($unknownTypeId) {
            $updatePastoralTypeQuery = $db->prepare('
                UPDATE pastoral p
                LEFT JOIN lu_pastoral_type t ON p.type_id = t.id
                SET p.type_id = :type_id
                WHERE t.id IS NULL;
            ');
            $log->info(sprintf("Updating pastoral type to 'Unknown Type' (id: %d)...", $unknownTypeId));

            try {
                $updatePastoralTypeQuery->execute(['type_id' => $unknownTypeId]);
                $log->info(sprintf("%d pastoral record types set to 'Unknown' (id: %d)", $updatePastoralTypeQuery->rowCount(), $unknownTypeId));
                $successCount = $updatePastoralTypeQuery->rowCount();
            } catch (PDOException $e) {
                $log->error(sprintf("Unable to set record type to 'Unknown' (id: %d)", $unknownTypeId), [$e]);
                $log->error($e->getMessage());
                $errorsOccurred = true;
            }
        }
    }

    $level   = $errorsOccurred ? Logger::ERROR : Logger::INFO;
    $message = sprintf("%s pastoral record types reassigned to 'Unknown Type'", $successCount);
    $log->log($level, $message);

    exit($errorsOccurred ? 1 : 0);
});

