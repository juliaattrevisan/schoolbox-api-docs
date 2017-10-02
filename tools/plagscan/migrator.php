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
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Model as PlagscanDocument;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('DocMan', [
    'verbose' => [false, false, false],
]);

try {
    $kernel->run(function ($di, $loop, $log, $opts) {

        /**
         * @var Injector        $di
         * @var LoopInterface   $loop
         * @var LoggerInterface $log
         * @var array           $opts
         */

        $log->debug("Plagscan HTML-OTFS Document Migrator");

        pcntl_signal(SIGCHLD, SIG_DFL);

        $lock = new Alaress_Lock('plagscan_migrator.lock');

        if (!$lock->acquire()) {
            $log->critical("Document migration already in process");
        } else {
            $plagscanMapper = PlagscanDocumentMapper::getInstance();

            $predicateBuilder = $plagscanMapper->getPredicateBuilder();

            $predicatePending = $predicateBuilder->notNull('html')
                ->isNull('storage_migrated_at')
                ->isNull('storage_failed_at');

            // Retrieve all unprocessed documents with HTML

            $documents = $plagscanMapper->findByPredicates($predicatePending)->getIterator();

            $failed = 0;
            $success = 0;
            $bytesTotal = 0;

            /** @var PlagscanDocument $document */
            foreach ($documents as $document) {
                $log->info("Migrating #{$document->getId()}: Plagscan ID {$document->getPlagscanId()}");
                if ($plagscanMapper->migrateHtml($document)) {
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

        $lock->release();
    });
} catch (Exception $e) {
    $log->critical('Exception Error: ' . $e->getMessage());
}