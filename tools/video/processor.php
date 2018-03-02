#!/usr/bin/php
<?php
/**
 * Video encoder and processor
 * This script should be run regularly by cron, it picks up any videos in the queue automatically converts them
 * into playable videos
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   2017 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

use Aura\Di\Container as Injector;
use Aura\Di\Exception\ServiceNotFound as ServiceNotFound;
use League\Flysystem\FileNotFoundException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\MapperFactory;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Video\Queue;
use Schoolbox\Video\QueueMapper;

require_once __DIR__ . '/../../conf/core/require.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Media Transcode', [
    'verbose' => [false, false, false],
]);


$kernel->run(function ($di, $loop, $log, $opts) {
    /**
     * @var Injector        $di
     * @var LoopInterface   $loop
     * @var LoggerInterface $log
     * @var array           $opts
     */

    // ------------------------------------------------------------------------------------------------------------
    // Get mappers
    // ------------------------------------------------------------------------------------------------------------

    try {
        /** @var IConfigLoader $configLoader */
        $configLoader = $di->get('config');

        /** @var MapperFactory $mapperFactory */
        $mapperFactory = $di->get('mapperFactory');
    } catch (ServiceNotFound $e) {
        $log->critical("Unknown service requested");
        exit(1);
    }

    /** @var QueueMapper $queueMapper */
    $queueMapper = $mapperFactory->get(QueueMapper::class);

    // ------------------------------------------------------------------------------------------------------------
    // Let kernel decide how to handle dead child processes
    // ------------------------------------------------------------------------------------------------------------

    pcntl_signal(SIGCHLD, SIG_DFL);

    // ------------------------------------------------------------------------------------------------------------
    // Allow only a single migration at a time
    // ------------------------------------------------------------------------------------------------------------

    $lock = new Alaress_Lock($configLoader->get('video_dir') . '/.transcode.lock');

    if (!$lock->acquire()) {
        $log->warning("Video processor is already running, checking timeout...");

        // Check timeout

        $timeout = (int)$configLoader->get('video_processing_timeout');

        if ($timeout <= 0) {
            $log->alert("Unable to proceed, timeout of media processing is disabled");
            exit(1);
        } elseif (($num_timed_out = $queueMapper->markAllProcessingAsTimedOut($timeout)) > 0) {
            $log->error("Error: {$num_timed_out} media item(s) timed out");
            $lock->killProcess();
            $lock->acquire();
        } else {
            $log->info("Unable to proceed, existing transcode still within timeout period");
            exit(1);
        }
    }

    // ------------------------------------------------------------------------------------------------------------
    // Check if there was anything to process
    // ------------------------------------------------------------------------------------------------------------

    /** @var GenericCollection $pendingQueue */
    $pendingQueue = $queueMapper->getPending();

    if ($pendingQueue->count() == 0) {
        $log->info("No pending media to transcode");
        $lock->release();
        exit;
    }

    // ------------------------------------------------------------------------------------------------------------
    // Iterate through all videos pending transcode
    // ------------------------------------------------------------------------------------------------------------

    $log->info("Processing {$pendingQueue->count()} pending media transcode(s)...");

    $countCurrent = 1;
    $countTotal   = $pendingQueue->count();
    $failed       = 0;
    $success      = 0;

    /** @var Queue $mediaCurrent */
    foreach ($pendingQueue as $mediaCurrent) {
        $hash = $mediaCurrent->getHash();

        if (empty($hash)) {
            // No storage hash recorded, unrecoverable error

            $log->error("Failed ({$mediaCurrent->getId()}): No valid file hash");
            $mediaCurrent->setMessage("Unrecoverable Error: No known file hash");
            $mediaCurrent->setStatus(QueueMapper::STATUS_FAILED);
            $queueMapper->save($mediaCurrent);
            $failed++;
        } else {
            try {
                // ------------------------------------------------------------------------------------------------
                // Display details of file being processed
                // ------------------------------------------------------------------------------------------------

                $storage = $mediaCurrent->getStorage();

                /** @noinspection PhpUndefinedMethodInspection */
                $fileSystem = get_class($storage->getFilesystem()->getAdapter());
                $fileName   = "{$storage->getFilename()}.{$storage->getExtension()}";
                $filePath   = $storage->getPath();

                $log->info("");
                $log->info("    - Processing Item:        {$countCurrent}/{$countTotal}");
                $log->info("    - Media Queue ID:         {$mediaCurrent->getId()}");
                $log->info("    - Storage Hash:           {$hash}");
                $log->info("    - Storage Filesystem:     {$fileSystem}");
                $log->info("    - Source File:            {$fileName}");
                $log->info("    - Source Path:            {$filePath}");

                $log->info(getDisplayFileSize($storage));
                $log->info(getDisplayFileMime($storage));

                // Update video to indicate processing has begun

                $mediaCurrent->setStatus(QueueMapper::STATUS_PROCESSING);
                $queueMapper->save($mediaCurrent);

                // ------------------------------------------------------------------------------------------------
                // Process the video
                // ------------------------------------------------------------------------------------------------

                if ($mediaCurrent->process($log) == true) {
                    $mediaCurrent->setStatus(QueueMapper::STATUS_COMPLETED);
                } else {
                    $mediaCurrent->setStatus(QueueMapper::STATUS_FAILED);
                }
            } catch (Exception $e) {
                $log->error($e->getMessage(), [$e]);
                $mediaCurrent->setStatus(QueueMapper::STATUS_FAILED);
            } finally {
                $queueMapper->save($mediaCurrent);
                $countCurrent++;
            }

            if ($mediaCurrent->getStatus() === QueueMapper::STATUS_FAILED) {
                $log->error("    - Transcode failed");
                $failed++;
            } else {
                $log->info("    - Transcode completed successfully");
                $success++;
            }
        }
    }

    // ------------------------------------------------------------------------------------------------------------
    // Display results
    // ------------------------------------------------------------------------------------------------------------

    $log->info("");

    if ($failed > 0) {
        $log->info("Processing completed with errors: {$success} succeeded, {$failed} failed");
    } else {
        $log->info("Processing completed: {$success} media items processed");
    }

    // ------------------------------------------------------------------------------------------------------------
    // Release file lock
    // ------------------------------------------------------------------------------------------------------------

    $lock->release();
});

// --------------------------------------------------------------------------------------------------------------------
// Helper functions
// --------------------------------------------------------------------------------------------------------------------

/**
 * Get pretty file size for display
 *
 * @param Storage $storage
 *
 * @return string
 */
function getDisplayFileSize(Storage $storage)
{
    try {
        $fileSize = $storage->getSize();
    } catch (FileNotFoundException $e) {
        return "";
    }

    $alaressByteFormat = new Alaress_Byte_Format();
    $alaressByteFormat->bytesToString($fileSize);

    return "    - Source File Size:       {$fileSize}";
}

/**
 * Get pretty file mime type for display
 *
 * @param Storage $storage
 *
 * @return string
 */
function getDisplayFileMime(Storage $storage)
{
    $fileMime     = "    - Detected Media Type:    ";
    $fileMimeType = "({$storage->getMimetype()->getMime()})";

    if ($storage->getMimetype()->isAudio()) {
        return "{$fileMime}Audio {$fileMimeType}";
    } elseif ($storage->getMimetype()->isVideo()) {
        return "{$fileMime}Video {$fileMimeType}";
    } elseif ($storage->getMimetype()->isImage()) {
        return "{$fileMime}Image {$fileMimeType}";
    }

    return "Unknown {$fileMimeType}";
}