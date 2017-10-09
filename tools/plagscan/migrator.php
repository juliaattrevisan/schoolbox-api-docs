#!/usr/bin/php
<?php

/**
 * Plagscan document migrator
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

use Aura\Di\Container as Injector;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Mapper as PlagscanDocumentMapper;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Migration\Mapper as PlagscanDocumentMigrationMapper;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Model as PlagscanDocument;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

const RESULT_FAILED      = 0;
const RESULT_SUCCESS     = 1;
const RESULT_FAILED_SIZE = 2;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$options = getopt("v::r::", ['verbose::', 'retry::']);

$kernel = new Schoolbox\Application\Daemon\Kernel('Plagscan', [
    'verbose' => isset($options['v']) || isset($options['verbose']) ? [false, false, false] : null,
    'retry'   => isset($options['r']) || isset($options['retry']) ? true : false,
]);

$lock = new Alaress_Lock('plagscan_migrator.lock');

try {
    $kernel->run(function ($di, $loop, $log, $opts) use ($lock) {
        /** @noinspection PhpUndefinedClassInspection */
        /**
         * @var Injector        $di
         * @var LoopInterface   $loop
         * @var LoggerInterface $log
         * @var array           $opts
         */

        // Indicate retry state

        $log->debug('Retry of failed documents is ' . ($opts['retry'] ? 'enabled' : 'disabled'));

        // Detect if a child process (SIGCHLD) fails to respond, if it does throw the process over to the
        // default kernel signal handler (SIG_DFL) which will take the appropriate default action (ignore it,
        // kill it) depending on what it was

        pcntl_signal(SIGCHLD, SIG_DFL);

        if (!$lock->acquire()) {
            $log->critical("Document migration already in process");
        } else {
            $plagscanMapper                  = PlagscanDocumentMapper::getInstance();
            $plagscanDocumentMigrationMapper = PlagscanDocumentMigrationMapper::getInstance();

            $predicateBuilder = $plagscanMapper->getPredicateBuilder();
            $predicatePending = $predicateBuilder->notNull('html');

            // Retrieve all documents with HTML

            $documents = $plagscanMapper->findByPredicates($predicatePending)->getIterator();

            $skipped    = 0;
            $failed     = 0;
            $success    = 0;
            $bytesTotal = 0;

            /** @var PlagscanDocument $document */
            foreach ($documents as $document) {
                if ($document->getPlagscanDocumentMigration()->isMigrated()) {
                    $log->debug('Skipping previously migrated document');
                    continue;
                }

                if ($document->getPlagscanDocumentMigration()->getStorageFailedAt() instanceof Alaress_Date_NullDate && !$opts['retry']) {
                    $log->debug('Skipping failed document');
                    $skipped++;
                }

                $log->info("Migrating #{$document->getId()}: Plagscan ID {$document->getPlagscanId()}");
                $result = migrateHtml($plagscanMapper, $plagscanDocumentMigrationMapper, $document);

                if ($result == RESULT_SUCCESS) {
                    $bytesTotal += strlen($document->getHtml());
                    $success++;
                } elseif ($result == RESULT_FAILED) {
                    $log->error('Document migration failed');
                    $failed++;
                } elseif ($result == RESULT_FAILED_SIZE) {
                    $log->error('Uploaded storage object size mismatch');
                    $failed++;
                }
            }

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
    echo "\nFatal Exception Error:\n";

    do {
        echo "\n{$e->getMessage()}";
        $e = $e->getPrevious();
    } while ($e instanceof \Exception);

    echo "\n\n";
} finally {
    $lock->release();
}

/**
 * Migrate the HTML contents from `plagscan_document` table into OTFS storage
 *
 * @param PlagscanDocumentMapper          $plagscanMapper                  The plagscan document mapper
 * @param PlagscanDocumentMigrationMapper $plagscanDocumentMigrationMapper The plagscan document migration mapper
 * @param PlagscanDocument                $document                        The Plagscan document to be migrated
 *
 * @return int
 */
function migrateHtml(PlagscanDocumentMapper $plagscanMapper, PlagscanDocumentMigrationMapper $plagscanDocumentMigrationMapper, PlagscanDocument $document): int
{
    // Otherwise save the HTML content into an OTFS storage object

    $storageMapper = StorageMapper::getInstance();

    // Grab the migration object

    $migration = $document->getPlagscanDocumentMigration();

    // Create temporary file to hold the HTML

    $storageObject = $storageMapper->acceptStream("plagscan-{$document->getPlagscanId()}.html", $document->getHtml());

    if ($storageObject instanceof IStorage) {
        // Uploading to storage succeeded

        $migration->setStorage($storageObject);
        $migration->setStorageMigratedAt(Alaress_Date_Format::now());

        $plagscanDocumentMigrationMapper->save($migration);

        if ($migration->getStorage()->getSize() == strlen($document->getHtml())) {
            // Blank out the document content if the move was successful

            $document->setHtml(null);
            $plagscanMapper->save($document);

            return RESULT_SUCCESS;
        } else {
            $migration->setStorageFailedAt(Alaress_Date_Format::now());
            $plagscanDocumentMigrationMapper->save($migration);

            return RESULT_FAILED_SIZE;
        }
    }

    // Uploading to storage failed

    $migration->setStorageFailedAt(Alaress_Date_Format::now());
    $plagscanDocumentMigrationMapper->save($migration);

    return RESULT_FAILED;
}