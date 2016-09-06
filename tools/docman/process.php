<?php
/**
 * Synergetic DocMan file uploader
 *
 * This script should be run regularily by cron, it picks up any pastoral care documents in the queue
 * and inserts them into the Synergetic DB
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   2016 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

use Schoolbox\Pastoral\UploadQueue\Mapper as PastoralUploadQueueMapper;
use Schoolbox\Pastoral\UploadQueue\Model as PastoralUploadQueueModel;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('DocMan', array(
    'verbose' => array(false,false,false),
));

// CREATE DIGEST APP
$kernel->run(function($di, $loop, $log, $opts) {

    // Only process if its actually enabled in config
    if (!Config::get('external_pastoral_docman')) {
        exit;
    }

    /**
     * @var Di\Container $di
     * @var LoopInterface $loop
     * @var LoggerInterface $log
     * @var array $opts
     */

    pcntl_signal(SIGCHLD, SIG_DFL);

    $pastoralUploadQueueMapper = new PastoralUploadQueueMapper();

    $lock = new Alaress_Lock('pastoral_upload.lock');
    if (!$lock->acquire()) {
        $log->critical("A video processor is already running, checking timeout");
        // Check timeout: if any processing video has been processing for more than
        // x minutes, then kill their process, mark as failed and start over.
        // (Should never be more than one video processing at once (per server)).
        $timeout = intval(Config::get('pastoral_processing_timeout'));
        if ($timeout <= 0) {
            $log->alert("Timeout of DocMan processing is disabled.");
            exit;
        } elseif (($num_timed_out = $pastoralUploadQueueMapper->markProcessingAsFailed($timeout)) > 0) {
            $log->warning("Processing of $num_timed_out video(s) has timed out - marking these as failed, and continuing");
            $lock->killProcess();
            $lock->acquire();
        } else {
            $log->info("No DocMan processing has timed out");
            exit;
        }
    }
    $log->info("Processing of DocMan started");

    /** @var PastoralUploadQueueModel[] $pendingQueue */
    $pendingQueue = $pastoralUploadQueueMapper->getPending();

    $failed = 0;
    $success = 0;
    foreach ($pendingQueue as $file) {

        $file_id = $file->getHash();

        $log->info("Starting process of file #$file_id");
        $file->setStatus(PastoralUploadQueueMapper::UPLOAD_STATUS_PROCESSING);
        $pastoralUploadQueueMapper->save($file);

        /* Process video */
        try {
            if ($file->process() == true) {
                $file->setStatus(PastoralUploadQueueMapper::UPLOAD_STATUS_COMPLETED);
            } else {
                $file->setStatus(PastoralUploadQueueMapper::UPLOAD_STATUS_FAILED);
            }
        } catch (Exception $e) {
            $log->error("$e", array($e));
            $file->setStatus(PastoralUploadQueueMapper::UPLOAD_STATUS_FAILED);
        }

        if ($file->getStatus() === PastoralUploadQueueMapper::UPLOAD_STATUS_FAILED) {
            $log->error("Process of media #$file_id failed");
            $failed++;
        } else {
            $log->info("Process of media #$file_id completed\n");
            $success++;
        }

        $file->setUpdatedAt(\Alaress_Date_Format::now());
        $pastoralUploadQueueMapper->save($file);
    }

    $lock->release();
    $log->info("Processing of DocMan completed: $success succeeded, $failed failed");
});
