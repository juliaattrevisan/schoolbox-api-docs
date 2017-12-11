#!/usr/bin/php
<?php

/**
 * Plagscan document migrator
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

use Alaress\Database\Connector\IConnector;
use Aura\Di\Container as Injector;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

const RESULT_FAILED      = 0;
const RESULT_SUCCESS     = 1;
const RESULT_FAILED_SIZE = 2;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$options = getopt("v::r::", ['verbose::', 'retry::']);

$kernel = new Schoolbox\Application\Daemon\Kernel('Plagscan Document Migration', [
    'verbose' => isset($options['v']) || isset($options['verbose']) ? [false, false, false] : null,
    'retry'   => isset($options['r']) || isset($options['retry']) ? true : false,
]);

try {
    /** @var IConfigLoader $config */
    $config = $kernel->getDependencyInjector()->get('config');

    $lock = new Alaress_Lock($config->get('stream_dir') . '/.plagscan_migrator.lock');

    /** @var IConnector $database */
    $database = $kernel->getDependencyInjector()->get('databaseInternal');

    $kernel->run(function ($di, $loop, $log, $opts) use ($lock, $database) {
        /** @noinspection PhpUndefinedClassInspection */
        /**
         * @var Injector        $di
         * @var LoopInterface   $loop
         * @var array           $opts
         * @var LoggerInterface $log
         */

        // Indicate retry state

        $log->debug('Retry of failed documents is ' . ($opts['retry'] ? 'enabled' : 'disabled'));

        // Detect if a child process (SIGCHLD) fails to respond, if it does throw the process over to the
        // default kernel signal handler (SIG_DFL) which will take the appropriate default action (ignore it,
        // kill it) depending on what it was

        pcntl_signal(SIGCHLD, SIG_DFL);

        if (!$lock->acquire()) {
            // Make sure we aren't locked

            $log->critical("Document migration already in process");
        } else {
            $skipped    = 0;
            $failed     = 0;
            $success    = 0;
            $bytesTotal = 0;
            $limit      = 50;
            $count      = 0;

            // Get a count of how many documents we need to action

            $countDocuments = $database->executeQuery(
                "
                    SELECT    COUNT(*) 
                    FROM        `plagscan_document` `p`
                    LEFT JOIN   `plagscan_document_migration` `m` ON 
                                `p`.`id` = `m`.`plagscan_document_record_id`  
                    WHERE       `p`.`id` NOT IN 
                                (
                                    SELECT    `m`.`plagscan_document_record_id`
                                    FROM      `plagscan_document_migration` `m` 
                                    WHERE     `m`.`storage_migrated_at` IS NOT NULL
                                ) AND 
                                `p`.`html` IS NOT NULL AND    
                                TRIM(`p`.`html`) <> '' 
                "
            )->fetchColumn();

            $log->info("{$countDocuments} documents pending migration");

            do {
                $documents = $database->getQueryIterator(
                    "
                        SELECT      `p`.`id`,
                                    `p`.`html`,
                                    `m`.* 
                        FROM        `plagscan_document` `p`
                        LEFT JOIN   `plagscan_document_migration` `m` ON 
                                    `p`.`id` = `m`.`plagscan_document_record_id`  
                        WHERE       `p`.`id` NOT IN 
                                    (
                                        SELECT    `m`.`plagscan_document_record_id`
                                        FROM      `plagscan_document_migration` `m` 
                                        WHERE     `m`.`storage_migrated_at` IS NOT NULL
                                    ) AND 
                                    `p`.`html` IS NOT NULL AND    
                                    TRIM(`p`.`html`) <> ''
                        LIMIT       :limit
                    ",
                    [
                        'limit'  => $limit
                    ]
                );

                // Wrap each batch of migrations in a transaction in case something goes foobar

                $database->beginTransaction();

                /** @var array $document */
                foreach ($documents as $document) {
                    if (!empty($document['storage_failed_at']) && !$opts['retry']) {
                        $log->debug("Skipping failed document when migrating `plagscan_document`.`id` {$document["id"]}");
                        $skipped++;
                        continue;
                    }

                    $result = migrateHtml($database, $document);

                    if ($result == RESULT_SUCCESS) {
                        $bytesTotal += strlen($document["html"]);
                        $success++;
                    } elseif ($result == RESULT_FAILED) {
                        $log->error("Document migration failed when migrating `plagscan_document`.`id` {$document["id"]}");
                        $failed++;
                    } elseif ($result == RESULT_FAILED_SIZE) {
                        $log->error("Uploaded storage object size mismatch when migrating `plagscan_document`.`id` {$document["id"]}");
                        $failed++;
                    }

                    $count++;
                }

                $database->commit();

                // Display a progress indicator between each batch

                if ($countDocuments > 0) {
                    $percentage = number_format($count / $countDocuments * 100, 2);
                    $megabytes  = number_format($bytesTotal / 1024 / 1024, 2);
                    $log->info("Migrating {$count}/{$countDocuments}: {$megabytes}MB Migrated {$percentage}%");
                }
            } while ($count < $countDocuments);

            if ($failed === 0 && $success === 0 && $skipped === 0) {
                $log->info("Nothing to migrate");
            } else {
                $message = "Migration process completed. {$success} Succeeded. {$skipped} Skipped. {$failed} Failed. {$bytesTotal} Bytes Migrated.";

                if ($failed > 0) {
                    $log->critical($message);
                } elseif ($skipped > 0) {
                    $log->warning($message);
                } else {
                    $log->info($message);
                }
            }
        }
    });
} catch (Exception $e) {
    // Something went wrong, rollback current transaction

    $database->rollback();

    // Logger isn't available here, just echoing out the exception

    echo "\nFatal Exception Error:\n";

    do {
        echo "\n{$e->getMessage()}";
        $e = $e->getPrevious();
    } while ($e instanceof Exception);

    echo "\n\n";
} finally {
    $lock->release();
}

/**
 * Migrate the HTML contents from `plagscan_document` table into OTFS storage
 *
 * @param IConnector $database
 * @param array      $document
 *
 * @return int
 */
function migrateHtml(IConnector $database, array $document): int
{
    /** @var string $now */
    $now = Alaress_Date_Format::now()->toIso8601String();

    // Otherwise save the HTML content into an OTFS storage object

    $storageMapper = StorageMapper::getInstance();

    // Create temporary file to hold the HTML

    $storageObject = $storageMapper->acceptStream("plagscan-{$document["id"]}.html", $document["html"]);

    if ($storageObject instanceof IStorage) {
        // Uploading to storage succeeded

        if ($storageObject->getSize() == strlen($document["html"]) &&
            md5($storageObject->read()) == md5($document["html"])) {
            // Create/update migration record

            if (empty($document["plagscan_document_record_id"])) {
                // Create migration record

                $database->executeQuery(
                    "
                        INSERT IGNORE INTO `plagscan_document_migration` (
                          `plagscan_document_record_id`, 
                          `storage_hash`, 
                          `storage_migrated_at`, 
                          `storage_failed_at`, 
                          `created_at`, 
                          `updated_at`, 
                          `deleted_at`
                        ) VALUES (
                          :plagscanDocumentId, 
                          :storageHash, 
                          :storageMigratedAt, 
                          :storageFailedAt, 
                          :createdAt, 
                          :updatedAt, 
                          :deletedAt
                        )
                    ",
                    [
                        'plagscanDocumentId' => $document["id"],
                        'storageHash'        => $storageObject->getHash(),
                        'storageMigratedAt'  => $now,
                        'storageFailedAt'    => null,
                        'createdAt'          => $now,
                        'updatedAt'          => $now,
                        'deletedAt'          => null,
                    ]
                );
            } else {
                // Update existing migration record

                $database->executeQuery(
                    "
                        UPDATE plagscan_document_migration SET
                            storage_hash = :storageHash, 
                            storage_migrated_at = :storageMigratedAt, 
                            updated_at = :updatedAt
                        WHERE plagscan_document_record_id = :plagscanDocumentId
                    ",
                    [
                        'storageHash'        => $storageObject->getHash(),
                        'storageMigratedAt'  => $now,
                        'updatedAt'          => $now,
                        'plagscanDocumentId' => $document["id"],
                    ]
                );
            }

            // Blank out the document content if the move was successful

            $database->executeQuery(
                "
                    UPDATE  `plagscan_document` 
                    SET     `html` = NULL,
                            `updated_at` = :updatedAt 
                    WHERE   `id` = :id
                ",
                [
                    'updatedAt' => $now,
                    'id'        => $document["id"],
                ]
            );

            return RESULT_SUCCESS;
        } else {
            // Migration failed due to size mismatch

            if (empty($document["plagscan_document_record_id"])) {
                // Insert new migration record

                $database->executeQuery(
                    "
                        INSERT IGNORE INTO `plagscan_document_migration`
                        (
                            `plagscan_document_record_id`, 
                            `storage_failed_at`, 
                            `created_at`, 
                            `updated_at`
                        ) 
                        VALUES
                        (
                            :plagscanDocumentId, 
                            :storageFailedAt, 
                            :createdAt, 
                            :updatedAt
                        ) 
                    ",
                    [
                        'plagscanDocumentId' => $document["id"],
                        'storageFailedAt'    => $now,
                        'createdAt'          => $now,
                        'updatedAt'          => $now,
                    ]
                );
            } else {
                // Update existing migration record

                $database->executeQuery(
                    "
                        UPDATE    `plagscan_document_migration`
                        SET       `storage_failed_at` = :storageFailedAt,
                                  `updated_at` = :updatedAt
                        WHERE     `id` = :id
                    ",
                    [
                        'storageFailedAt' => $now,
                        'updatedAt'       => $now,
                        'id'              => $document["id"],
                    ]
                );
            }

            return RESULT_FAILED_SIZE;
        }
    }

    // Uploading to storage failed for some other reason

    $database->executeQuery(
        "
            UPDATE    `plagscan_document_migration` 
            SET       `storage_failed_at` = :storageFailedAt,
                      `updated_at` = :updatedAt
            WHERE     `id` = :id
        ",
        [
            'storageFailedAt' => $now,
            'updatedAt'       => $now,
            'id'              => $document["id"],
        ]
    );

    return RESULT_FAILED;
}