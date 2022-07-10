<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Video\CommandLineTool;

use Alaress_Byte_Format;
use Exception;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\Filesystem;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\MapperFactory;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Video\Queue;
use Schoolbox\Video\QueueMapper;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;

/**
 * Video encoder and processor.
 *
 * This script should be run regularly by cron, it picks up any videos in the
 * queue automatically converts them into playable videos.
 */
class Processor extends CliTool\App
{
    public const LOCK_NAME = 'video_transcode';


    /**
     * Default timeout for symfony lock: falsey values trigger exception
     */
    private const DEFAULT_TIMEOUT = 10 * 60;


    /** @inheritDoc */
    public function getRequiredProcessUser(): ?string
    {
        return 'www-data';
    }


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
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
            exit(1);
        }

        /** @var QueueMapper $queueMapper */
        $queueMapper = $mapperFactory->get(QueueMapper::class);

        // ------------------------------------------------------------------------------------------------------------
        // Allow only a single migration at a time
        // ------------------------------------------------------------------------------------------------------------
        $timeout = (int)$configLoader->get('video_processing_timeout');

        /* Force default timeout since falsey timeout means something in symfony lock
         */
        if (!$timeout) {
            $timeout = self::DEFAULT_TIMEOUT;
        }

        try {
            $lock = $this->acquireLock(self::LOCK_NAME, $timeout);
        } catch (\RuntimeException $e) {
            exit(1);
        }
        $this->setMaxExecutionTime($timeout);

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

        /** @var Filesystem $storageFilesystem */
        $storageFilesystem = $this->getDi()->get('filesystem.storage');

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

                    $fileSystem = $this->getConfig()->get(ConfigMapper::NAME_OTFS_ADAPTER);
                    $fileName   = "{$storage->getFilename()}.{$storage->getExtension()}";
                    $filePath   = FilesystemProxy::getPathFromHash($storage->getHash());

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
                    // Also clear the message field
                    $mediaCurrent->setStatus(QueueMapper::STATUS_PROCESSING);
                    $mediaCurrent->setMessage('');
                    $queueMapper->save($mediaCurrent);

                    // ------------------------------------------------------------------------------------------------
                    // Process the video
                    // ------------------------------------------------------------------------------------------------

                    if ($mediaCurrent->process($storageFilesystem, $log) == true) {
                        $mediaCurrent->setStatus(QueueMapper::STATUS_COMPLETED);
                    } else {
                        $mediaCurrent->setStatus(QueueMapper::STATUS_FAILED);
                    }
                } catch (Exception $e) {
                    $log->error($e->getMessage(), ['exception' => $e]);
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

            /* Refresh lock while we're still processing; keep using another
             * $timeout seconds until we're done, or until we died
             */
            try {
                $log->info('Refreshing lock');

                $lock->refresh();
            } catch (LockConflictedException | LockAcquiringException $e) {
                $log->warning('Could not refresh lock: aborting');
                break;
            }

            // Refresh process lock, or break if it can't be refreshed
            if ($lock->isExpired()) {
                $log->warning('Lock expired during processing: aborting');
                break;
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
        } catch (UnableToReadFile $e) {
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
}
