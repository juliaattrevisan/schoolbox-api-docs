#!/usr/bin/php
<?php
/**
 * Video encoder and processor
 * This script should be run regularly by cron, it picks up any videos in the queue automatically converts them
 * into playable videos
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Aura\Di\Exception\ServiceNotFound ;
use League\Flysystem\FileNotFoundException;
use Schoolbox\GenericCollection;
use Schoolbox\MapperFactory;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Video\Queue;
use Schoolbox\Video\QueueMapper;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App{


    /** @inheritDoc */
    public function run(): void
    {
        // ------------------------------------------------------------------------------------------------------------
        // Get mappers
        // ------------------------------------------------------------------------------------------------------------

        $configLoader = $this->getConfig();
        $log          = $this->getLogger();

        try {
            /** @var MapperFactory $mapperFactory */
            $mapperFactory = $this->getDi()->get('mapperFactory');
        } catch (ServiceNotFound $e) {
            $log->critical("Unknown service requested", ['exception'=>$e]);
            exit(1);
        }

        /** @var QueueMapper $queueMapper */
        $queueMapper = $mapperFactory->get(QueueMapper::class);

        // ------------------------------------------------------------------------------------------------------------
        // Allow only a single migration at a time
        // ------------------------------------------------------------------------------------------------------------

        $lock = new Alaress_Lock($configLoader->get('stream_dir') . '/.video_transcode_'.INSTANCE_ID.'.lock');

        if (!$lock->acquire()) {
            $log->warning("Video processor is already running, checking timeout...");

            // Check timeout
            $timeout = (int)$configLoader->get('video_processing_timeout');

            if ($timeout <= 0) {
                $log->alert("Unable to proceed, timeout of media processing is disabled");
                exit(1);
            }

            // If videos exceeded the timeout, and we timed all videos out,
            // kill the process (otherwise we are still processing videos)
            $totalProcessing = $queueMapper->countByColumn('status', $queueMapper::STATUS_PROCESSING);
            $numTimedOut     = $queueMapper->markAllProcessingAsTimedOut($timeout);
            if ($numTimedOut > 0) {
                $log->error("Error: {$numTimedOut} media item(s) timed out");
                if (($totalProcessing - $numTimedOut) === 0) {
                    $log->info("Existing transcode timed out and no further media item(s) processing: terminating existing processor");
                    $lock->killProcess();
                    $lock->acquire();
                } else {
                    $log->info("Unable to proceed, existing transcode running and still has media to process");
                    exit(1);
                }
            } else {
                $log->info("Unable to proceed, existing transcode running and no media item(s) timed out");
                exit(1);
            }
        }

        // ------------------------------------------------------------------------------------------------------------
        // Check if there was anything to process
        // ------------------------------------------------------------------------------------------------------------

        /** @var GenericCollection $pendingQueue */
        $pendingQueue = $queueMapper->getPending();

        if ($pendingQueue->count() === 0) {
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

                    $log->info($this->getDisplayFileSize($storage));
                    $log->info($this->getDisplayFileMime($storage));

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
                    $log->error($e->getMessage(), ['exception'=>$e]);
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
    }


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
    private function getDisplayFileSize(Storage $storage): string
    {
        try {
            $fileSize = $storage->getSize();
        } catch (FileNotFoundException $e) {
            return "";
        }

        $fileSize = Alaress_Byte_Format::bytesToString($fileSize);

        return "    - Source File Size:       {$fileSize}";
    }

    /**
     * Get pretty file mime type for display
     *
     * @param Storage $storage
     *
     * @return string
     */
    private function getDisplayFileMime(Storage $storage): string
    {
        $fileMime     = "    - Detected Media Type:    ";
        $fileMimeType = "({$storage->getMimetype()->getMime()})";

        if ($storage->getMimetype()->isAudio()) {
            return "{$fileMime}Audio {$fileMimeType}";
        }
        if ($storage->getMimetype()->isVideo()) {
            return "{$fileMime}Video {$fileMimeType}";
        }
        if ($storage->getMimetype()->isImage()) {
            return "{$fileMime}Image {$fileMimeType}";
        }

        return "Unknown {$fileMimeType}";
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]);
$kernel->runApp();

