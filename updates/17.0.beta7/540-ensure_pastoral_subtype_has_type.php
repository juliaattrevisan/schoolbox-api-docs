<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

/**
 * Ensures that pastoral 'sub-types' belong to a 'type'.
 *
 * At the moment, this relationship is optional in the schema, it should be mandatory
 */
use Psr\Log\LoggerInterface;
use Monolog\Logger;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Ensure `lu_pastoral_category.pastoral_type_id` referential integrity', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);


/** @noinspection PhpUnusedParameterInspection */
$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {

    $db             = Database::getInstance();
    $errorsOccurred = false;
    $successCount   = 0;

    $log->info('Searching for pastoral sub-types without a type');
    $selectSubTypesWithoutTypes = $db->prepare('
        SELECT st.id, st.name FROM lu_pastoral_category st
        LEFT JOIN lu_pastoral_type t ON t.id = st.pastoral_type_id
        WHERE t.id IS NULL;
    ');
    $selectSubTypesWithoutTypes->execute();

    if (!$selectSubTypesWithoutTypes->rowCount()) {
        $log->info('No pastoral sub-types found without a type. OK.');
    } else {
        $log->info(sprintf("%d pastoral sub-types found without a type. Creating new parent 'type'",
            $selectSubTypesWithoutTypes->rowCount()));

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $selectSubTypesWithoutTypes->fetch(PDO::FETCH_ASSOC)) {
            $log->debug(sprintf("SubType: id: '%d', name: '%s'", $row['id'], $row['name']));
        }

        // Select the Alaress User
        $log->info("Inserting 'Unknown Sub-Type type'...");
        $insertTypeQuery = $db->prepare("
            INSERT INTO lu_pastoral_type (name) VALUES ('Unknown Sub-Type type')
        ");

        $unknownTypeId = null;
        try {
            $insertTypeQuery->execute();
            $unknownTypeId = $db->lastInsertId();
            $log->info(sprintf("'Unknown Sub-Type type' successfully inserted (id: %d)", $unknownTypeId));
        } catch (PDOException $e) {
            $log->error("Unable to insert 'Unknown Sub-Type type'", [$e]);
            $log->error($e->getMessage());
            $errorsOccurred = true;
        }


        if ($unknownTypeId) {
            $updatePastoralSubType = $db->prepare('
                UPDATE lu_pastoral_category st
                LEFT JOIN lu_pastoral_type t ON st.pastoral_type_id = t.id
                SET st.pastoral_type_id = :type_id
                WHERE t.id IS NULL;
            ');
            $log->info(sprintf("Updating pastoralSubType.type to 'Unknown Sub-Type type' (id: %d)...", $unknownTypeId));

            try {
                $updatePastoralSubType->execute(['type_id' => $unknownTypeId]);
                $log->info(sprintf("%d pastoral sub-types successfully linked to 'Unknown Sub-Type type' (id: %d)",
                    $updatePastoralSubType->rowCount(), $unknownTypeId));
                $successCount = $updatePastoralSubType->rowCount();
            } catch (PDOException $e) {
                $log->error(sprintf("Unable to set type to 'Unknown Sub-Type type' (id: %d)", $unknownTypeId), [$e]);
                $log->error($e->getMessage());
                $errorsOccurred = true;
            }
        }
    }

    $level   = $errorsOccurred ? Logger::ERROR : Logger::INFO;
    $message = sprintf("%d pastoral sub-type types set to 'Unknown Sub-Type type'", $successCount);
    $log->log($level, $message);

    exit($errorsOccurred ? 1 : 0);
});

