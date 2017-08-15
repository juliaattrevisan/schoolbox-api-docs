<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

/**
 * An off-by-one logic error has cause pastoral_revision record timestamp fields to match the pastoral record
 * before the change, not after the change (as it the case with the other fields).
 *
 * This was caused because the mapper does not natively hydrate a model's timestamps after saving NOW() to the DB.
 * If you just copy the saved model, the timestamps do not match the database.
 *
 * This means all revision.updated_at values need to be copied from the later revision to the immediately preceding
 * revision, and the last revision's updated_at value taken from the current pastoral.updated_at
 *
 * We can fix the analogous `created_at` bug by a simple SQL because it doesn't change per record
 */
use Psr\Log\LoggerInterface;
use Monolog\Logger;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Fix `pastoral_revision`.`updated_at` off-by-one error', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);


/** @noinspection PhpUnusedParameterInspection */
$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {

    $db             = Database::getInstance();
    $countSuccess   = 0;
    $countFailure   = 0;

    // Since we are mutating log data, err on side of caution when fetching which records suffer this specific error
    $selectPastoralRecords = $db->prepare('
        SELECT p.id, p.updated_at FROM pastoral p
        JOIN pastoral_revision r on r.root_id = p.id
        WHERE r.updated_at IS NULL
        AND r.created_at IS NULL
        GROUP BY p.id
        HAVING count(p.id) = 1;
    ');

    $selectRevisions = $db->prepare('
        SELECT id, updated_at FROM pastoral_revision
        WHERE root_id = :root_id
        ORDER BY root_id, updated_at DESC;
    ');

    $updateRevision = $db->prepare('
        UPDATE pastoral_revision
        SET updated_at = :updated_at
        WHERE id = :id
    ');

    $log->info('Fetching all pastoral records where revision timestamps are off-by-one');
    $selectPastoralRecords->execute();

    if (!$selectPastoralRecords->rowCount()) {
        $log->info('No pastoral records found with broken revision timestamps. OK.');
    } else {
        $pastoralRecords = $selectPastoralRecords->fetchAll(PDO::FETCH_ASSOC);
        $log->info(sprintf('%d pastoral records found with broken revision timestamps. Repairing `updated_at` value',
            $selectPastoralRecords->rowCount()));

        // For each pastoral record, fetch all its revisions
        foreach($pastoralRecords as $row)
        {
            $pastoralId        = $row['id'];
            $pastoralUpdatedAt = $row['updated_at'];

            $log->info(sprintf("Repairing pastoral (id: %d), updated_at: '%s'", $pastoralId, $pastoralUpdatedAt));
            $selectRevisions->execute(['root_id' => $pastoralId]);
            $revisionCount = $selectRevisions->rowCount();
            $log->debug(sprintf('%d revisions found', $revisionCount));

            // The latest revision should take the value from the pastoral record
            $newUpdatedAt = $pastoralUpdatedAt;

            // For each revision, from latest to oldest
            $revisions = $selectRevisions->fetchAll(PDO::FETCH_ASSOC);
            foreach($revisions as $revision)
            {
                $revisionId        = $revision['id'];
                $revisionUpdatedAt = $revision['updated_at'];

                try {
                    $updateRevision->execute([
                        'updated_at' => $newUpdatedAt,
                        'id'         => $revisionId,
                    ]);
                    $log->debug(sprintf("Revision (id: %d) `updated_at` successfully changed from '%s' to '%s'",
                        $revisionId, $revisionUpdatedAt ?: 'NULL', $newUpdatedAt));
                    $countSuccess++;
                } catch (PDOException $e) {
                    $log->error(sprintf("Failed to update revision (id: %d) `updated_at` from '%s' to '%s'",
                        $revisionId, $revisionUpdatedAt ?: 'NULL', $newUpdatedAt), [$e]
                    );
                    $log->error($e->getMessage());
                    $countFailure++;
                }

                // Next older revision should take this revision's updated_at value
                $newUpdatedAt = $revisionUpdatedAt;
            }
        }
    }

    $level   = $countFailure ? Logger::ERROR : Logger::INFO;
    $message = sprintf('%d `pastoral_revision.updated_at` timestamps updated, %d failures', $countSuccess, $countFailure);
    $log->log($level, $message);

    exit($countFailure ? 1 : 0);
});

