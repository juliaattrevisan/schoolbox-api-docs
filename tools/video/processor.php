<?php
/**
 * Video encoder and processor
 *
 * This script should be run regularily by cron, it picks up any videos in the queue
 * automatically converts them into playable videos
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

use Schoolbox\Video\Queue;
use Schoolbox\Video\QueueMapper;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Video', array(
    'verbose' => array(false,false,false),
));


// CREATE DIGEST APP
$kernel->run(function($di, $loop, $log, $opts) {


    /**
     * @var Di\Container $di
     * @var LoopInterface $loop
     * @var LoggerInterface $log
     * @var array $opts
     */

    pcntl_signal(SIGCHLD, SIG_DFL);

    $mapper = new QueueMapper();
    $lock = new Alaress_Lock(Cfg::get('video_dir') . '/.lock');
    if (!$lock->acquire()) {
        $log->critical("A video processor is already running, checking timeout");
        // Check timeout: if any processing video has been processing for more than
        // x minutes, then kill their process, mark as failed and start over.
        // (Should never be more than one video processing at once (per server)).
        $timeout = intval(Config::get('video_processing_timeout'));
        if ($timeout <= 0) {
            $log->alert("Timeout of video processing is disabled.");
            exit;
        } elseif (($num_timed_out = $mapper->markAllProcessingAsTimedOut($timeout)) > 0) {
            $log->warning("Processing of $num_timed_out video(s) has timed out - marking these as failed, and continuing");
            $lock->killProcess();
            $lock->acquire();
        } else {
            $log->info("No video processing has timed out");
            exit;
        }
    }
    $log->info("Processing of videos started");

    /** @var Queue[] $pendingQueue */
    $pendingQueue = $mapper->getPending();

    $failed = 0;
    $success = 0;
    foreach ($pendingQueue as $vid) {

        $file_id = $vid->getHash();

        /* Update video to indicate processing has begun */
        $log->info("Starting process of media #$file_id");
        $vid->setStatus(QueueMapper::STATUS_PROCESSING);
        $mapper->save($vid);

        /* Process video */
        try {
            if ($vid->process() == true) {
                $vid->setStatus(QueueMapper::STATUS_COMPLETED);
            } else {
                $vid->setStatus(QueueMapper::STATUS_FAILED);
            }
        } catch (Exception $e) {
            $log->error("$e", array($e));
            $vid->setStatus(QueueMapper::STATUS_FAILED);
        }

        if ($vid->getStatus() === QueueMapper::STATUS_FAILED) {
            $log->error("Process of media #$file_id failed");
            $failed++;
        } else {
            $log->info("Process of media #$file_id completed\n");
            $success++;
        }

        $mapper->save($vid);
    }

    $lock->release();
    $log->info("Processing of videos completed: $success succeeded, $failed failed");
});
