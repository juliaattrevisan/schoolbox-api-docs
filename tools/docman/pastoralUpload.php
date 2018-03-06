<?php

/**
 * Synergetic DocMan file uploader
 *
 * This script should be run regularly by cron, it picks up any pastoral care documents in the queue
 * and inserts them into the Synergetic DB
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

use Aura\Di\Container as Injector;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Pastoral\UploadQueue\Mapper as PastoralUploadQueueMapper;
use Schoolbox\Pastoral\UploadQueue\Model as PastoralUploadQueueModel;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('DocMan', array(
    'verbose' => array(false, false, false),
));

$kernel->run(function ($di, $loop, $log, $opts) {

    /**
     * @var Injector $di
     * @var LoopInterface $loop
     * @var LoggerInterface $log
     * @var array $opts
     */

    // Only process if pastoral docman writeback is actually enabled in config

    if (!$di->get('config')->get("external_pastoral_upload")) {
        exit;
    }

    pcntl_signal(SIGCHLD, SIG_DFL);

    $pastoralUploadQueueMapper = new PastoralUploadQueueMapper();

    $lock = new Alaress_Lock('pastoral_upload.lock');

    if (!$lock->acquire()) {
        $log->critical("A pastoral uploader is already running, checking timeout.");

        // Check existing upload process for timeout (should never be more than uploaded more than once).
        $timeout = intval($di->get('config')->get('pastoral_upload_timeout'));

        if ($timeout <= 0) {
            $log->alert("Timeout of pastoral uploading is disabled.");
            exit;
        } elseif (($num_timed_out = $pastoralUploadQueueMapper->markProcessingAsFailed($timeout)) > 0) {
            $log->warning("Processing of $num_timed_out pastoral upload(s) has timed out - marking these as failed, and continuing");
            $lock->killProcess();
            $lock->acquire();
        } else {
            $log->info("No pastoral uploader has timed out.");
            exit;
        }
    }

    $log->info("Uploading of pastoral files started");

    $pendingQueue = $pastoralUploadQueueMapper->getPending();

    $failed = 0;
    $success = 0;

    /** @var PastoralUploadQueueModel $file */
    foreach ($pendingQueue as $file) {

        if (!empty($file->getStorage())) {
            $hash = $file->getStorage()->getHash();

            $log->info("Starting process of file '{$hash}'");

            // Mark file as processing so another daemon get started and try to duplicate processing

            $file->setStatus(PastoralUploadQueueMapper::UPLOAD_STATUS_PROCESSING);

            try {
                $pastoralUploadQueueMapper->save($file);
            } catch (PDOException $e) {
                $log->alert("File '{$hash}' failed:".$e->getMessage());
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
                $log->info("Uploading of file '{$hash}' completed\n");
                $success++;
            }
        } else {
            $log->error("Uploading of upload queue item '{$file->getId()}' failed. No storage object found");
            $file->setStatus(PastoralUploadQueueMapper::UPLOAD_STATUS_FAILED);
        }

        $pastoralUploadQueueMapper->save($file);
    }

    $lock->release();
    $log->info("Processing of pastoral upload completed: $success succeeded, $failed failed");
});
