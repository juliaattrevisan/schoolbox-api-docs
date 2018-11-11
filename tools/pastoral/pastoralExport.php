<?php

/**
 * Synergetic Pastoral Exporter
 *
 * This script manually syncs all pastoral care records within Schoolbox to
 * the external pastoral care system, if one is configured.
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

use Aura\Di\Container as Injector;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as PastoralModel;
use Schoolbox\Pastoral\UploadQueue\Mapper as PastoralUploadQueueMapper;
use Schoolbox\Pastoral\UploadQueue\Model as PastoralUploadQueueModel;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$opts   = getopt('v');
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Pastoral Export',
    [
        'verbose' => $opts['v'] ?? [],
    ]
);

$kernel->run(function ($di, $loop, $log, $opts) {

    /**
     * @var Injector $di
     * @var LoopInterface $loop
     * @var LoggerInterface $log
     * @var array $opts
     */

    // Only process if pastoral docman writeback is actually enabled in config

    if (!$di->get('config')->get("external_pastoral")) {
        $log->warning('Pastoral external is disabled, exiting...');
        exit;
    }

    pcntl_signal(SIGCHLD, SIG_DFL);

    $pastoralMapper            = PastoralMapper::getInstance();
    $pastoralUploadQueueMapper = PastoralUploadQueueMapper::getInstance();
    $externalPastoral = new External_Pastoral();

    $lock = new Alaress_Lock('pastoral_export.' . INSTANCE_ID . '.lock');

    if (!$lock->acquire()) {
        $log->warning("A pastoral export is currently in progress, exiting...");
        exit;
    }


    // Retrieve all pastoral records and re-save
    $log->info("Beginning export of pastoral records...");
    $pastoralRecords = $pastoralMapper->findAll();

    /** @var PastoralModel $pastoralRecord */
    foreach ($pastoralRecords as $pastoralRecord) {
        $log->debug("Syncing pastoral record (Pastoral ID #{$pastoralRecord->getId()})...");
        $externalPastoral->syncPastoral($pastoralRecord);
        $log->debug("Pastoral record synced (External ID #{$pastoralRecord->getExternalId()})...");
    }
    $log->info(sprintf("%s pastoral records exported", count($pastoralRecords)));


    // Revert all docman uploads to pending (only if attachment upload is on)
    if (!$di->get('config')->get("external_pastoral_upload")) {
        $log->info('Pastoral external attachment upload is disabled: not uploading attachments');

    } else {
        $log->info("Beginning reversion of all Docman records to pending...");
        $pastoralUploads = $pastoralUploadQueueMapper->findAll();
        foreach ($pastoralUploads as $pastoralUpload) {
            $pastoralUpload->setStatus(PastoralUploadQueueMapper::UPLOAD_STATUS_PENDING);
            $pastoralUploadQueueMapper->save($pastoralUpload);
        }
        $log->info(sprintf("%s Docman records reverted", count($pastoralUploads)));


        // Re-upload all documents
        $pendingQueue = $pastoralUploadQueueMapper->getPending();
        $failed  = 0;
        $success = 0;

        $log->info("Beginning re-upload of all documents...");

        /** @var PastoralUploadQueueModel $file */
        foreach ($pendingQueue as $file) {

            if (!empty($file->getStorage())) {
                $hash = $file->getStorage()->getHash();

                $log->debug("Starting process of file '{$hash}'");

                // Mark file as processing so another daemon get started and try to duplicate processing
                $file->setStatus(PastoralUploadQueueMapper::UPLOAD_STATUS_PROCESSING);
                try {
                    $pastoralUploadQueueMapper->save($file);
                } catch (PDOException $e) {
                    $log->alert("File '{$hash}' failed:" . $e->getMessage());
                    $failed++;
                    continue;
                }


                // Upload file to DocMan
                try {
                    $file->uploadPastoralDocument();
                    $file->setStatus(PastoralUploadQueueMapper::UPLOAD_STATUS_COMPLETED);
                } catch (Exception $e) {
                    $log->error("$e", array($e));
                    $file->setStatus(PastoralUploadQueueMapper::UPLOAD_STATUS_FAILED);
                }

                if ($file->getStatus() === PastoralUploadQueueMapper::UPLOAD_STATUS_FAILED) {
                    $log->error("Uploading of file '{$hash}' failed");
                    $failed++;
                } else {
                    $log->debug("Uploading of file '{$hash}' completed\n");
                    $success++;
                }
            } else {
                $log->error("Uploading of upload queue item '{$file->getId()}' failed. No storage object found");
                $file->setStatus(PastoralUploadQueueMapper::UPLOAD_STATUS_FAILED);
            }

            $pastoralUploadQueueMapper->save($file);
        }

        $log->info("Processing of pastoral upload completed: $success succeeded, $failed failed");
    }

    $lock->release();
});
