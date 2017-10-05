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
use Schoolbox\File\Exception as FileException;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Mapper as PlagscanDocumentMapper;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Model as PlagscanDocument;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('DocMan', [
    'verbose' => [false, false, false],
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

        $log->debug("Plagscan HTML-OTFS Document Migrator");

        // Detect if a child process (SIGCHLD) fails to respond, if it does throw the process over to the
        // default kernel signal handler (SIG_DFL) which will take the appropriate default action (ignore it,
        // kill it) depending on what it was

        pcntl_signal(SIGCHLD, SIG_DFL);

        if (!$lock->acquire()) {
            $log->critical("Document migration already in process");
        } else {
            $plagscanMapper = PlagscanDocumentMapper::getInstance();

            $predicateBuilder = $plagscanMapper->getPredicateBuilder();

            $predicatePending = $predicateBuilder
                ->notNull('html')
                ->isNull('storage_migrated_at')
                ->isNull('storage_failed_at');

            // Retrieve all unprocessed documents with HTML

            $documents = $plagscanMapper->findByPredicates($predicatePending)->getIterator();

            $failed     = 0;
            $success    = 0;
            $bytesTotal = 0;

            /** @var PlagscanDocument $document */
            foreach ($documents as $document) {
                $log->info("Migrating #{$document->getId()}: Plagscan ID {$document->getPlagscanId()}");
                if (migrateHtml($plagscanMapper, $document)) {
                    $bytesTotal += strlen($document->getHtml());
                    $success++;
                } else {
                    $log->error('Document Migration Failed');
                    $failed++;
                }
            }

            if ($failed === 0 && $success === 0) {
                $log->warning("Nothing to migrate");
            } else {
                if ($failed > 0) {
                    $log->error("Migration process completed. {$success} Succeeded. {$failed} Failed. {$bytesTotal} Bytes Migrated.");
                } else {
                    $log->info("Migration process completed. {$success} Succeeded. {$failed} Failed. {$bytesTotal} Bytes Migrated.");
                }
            }
        }

    });
} catch (Exception $e) {
    $log->critical('Exception Error: ' . $e->getMessage(), [$e]);
} finally {
    $lock->release();
}

/**
 * Migrate the HTML contents from `plagscan_document` table into OTFS storage
 *
 * @param PlagscanDocumentMapper $plagscanMapper The plagscan document mapper
 * @param PlagscanDocument       $document       The Plagscan document to be migrated
 * @param bool                   $retry          Flag used to determine if failed documents should be reattempted
 *
 * @return bool
 */
function migrateHtml(PlagscanDocumentMapper $plagscanMapper, PlagscanDocument $document, bool $retry = false): bool
{
    // If the document had already failed, and we aren't retrying failed documents, fail again.

    if (!$document->getStorageFailedAt() instanceof Alaress_Date_NullDate && !$retry) {
        return false;
    }

    // Shortcut if the document has already been migrated

    if ($document->isMigrated()) {
        return true;
    }

    // If the document does not have HTML mark it as migrated so we don't attempt again

    if (!$document->hasHtml()) {
        $document->setStorageMigratedAt(Alaress_Date_Format::now());
        $plagscanMapper->save($document);

        return true;
    }

    // Otherwise save the HTML content into an OTFS storage object

    $storageMapper = StorageMapper::getInstance();

    // Create temporary file to hold the HTML

    $filename = "plagscan-{$document->getPlagscanId()}.html";
    $file     = tmpfile();
    $metadata = stream_get_meta_data($file);
    fwrite($file, $document->getHtml());
    fseek($file, 0);

    try {
        $storageObject = $storageMapper->accept($filename, $metadata["uri"]);

        // Uploading to storage succeeded

        $document->setStorage($storageObject);
        $document->setStorageMigratedAt(Alaress_Date_Format::now());
        $plagscanMapper->save($document);
        fclose($file);

        return true;
    } catch (FileException $e) {
        // Uploading to storage failed

        $document->setStorageFailedAt(Alaress_Date_Format::now());
        $plagscanMapper->save($document);
        fclose($file);

        return false;
    }
}