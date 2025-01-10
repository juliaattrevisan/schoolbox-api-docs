<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\CommandLineTool;

use Exception;
use External_Pastoral;
use PDOException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Pastoral;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockInterface;

/**
 * Pastoral attachment uploader: uploads files from Schoolbox to the SIS'
 * pastoral document manager.
 */
class AttachmentUploader extends CliTool\App
{
    public const LOCK_NAME = 'pastoral_upload';


    /**
     * Default timeout for symfony lock: falsey values trigger exception
     */
    private const DEFAULT_TIMEOUT = 10 * 60;


    // --help: print help
    private const OPT_LONG_HELP = 'help';

    /** @var Pastoral\UploadQueue\Mapper */
    private $pastoralUploadQueueMapper;

    /** @var External_Pastoral */
    private $externalPastoral;


    /**
     * @var LockInterface
     */
    private LockInterface $lock;


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }

    /** @inheritDoc */
    public function setup(array $appOptions, ContainerInterface $di, $outStream, LoggerInterface $logger, bool $perform): CliTool\App
    {
        parent::setup($appOptions, $di, $outStream, $logger, $perform);

        // Only process if pastoral docman writeback is actually enabled in config
        if (!$this->getConfig()->get(ConfigMapper::NAME_PASTORAL_EXTERNAL_UPLOAD)) {
            $this->getLogger()->info('Pastoral Attachment Upload is disabled in config; exiting');
            exit;
        }
        $this->pastoralUploadQueueMapper = Pastoral\UploadQueue\Mapper::getInstance();
        $this->externalPastoral          = new External_Pastoral();
        return $this;
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Upload Pastoral Care attachments into synergetic's docman functionality.

Usage: pastoralUpload.php


HELP;
            exit;
        }

        $this->getLogger()->info('Uploading of pastoral files started');
        $this->checkOverlappingExecution();
        $this->uploadPending();
        $this->lock->release();
    }


    /**
     * Checks whether the execution of this App is overlapping with a prior execution.
     *
     * Returns the acquired lock
     */
    private function checkOverlappingExecution(): void
    {
        $timeout = (int)$this->getConfig()->get('pastoral_upload_timeout');

        /* Force default timeout since falsey timeout means something in symfony lock
         */
        if (!$timeout) {
            $timeout = self::DEFAULT_TIMEOUT;
        }

        try {
            $this->lock = $this->acquireLock(self::LOCK_NAME, $timeout);
        } catch (\RuntimeException $e) {
            exit(1);
        }
        $this->setMaxExecutionTime($timeout);
    }


    /**
     * Find and upload pending documents to external SIS
     */
    private function uploadPending(): void
    {
        $success = $failed = 0;
        $pendingUploads = $this->pastoralUploadQueueMapper->getPending();
        $this->getLogger()->debug('Found ' . count($pendingUploads) . ' pending uploads');
        foreach ($pendingUploads as $pendingUpload) {
            try {
                $this->processPendingUpload($pendingUpload) ? $success++ : $failed++;
            } catch (PDOException $e) {
                $this->getLogger()->error($e->getMessage(), ['exception' => $e]);
                $failed++;
            }

            /* Refresh lock while we're still processing; keep using another
             * $timeout seconds until we're done, or until we died
             */
            try {
                $this->getLogger()->info('Refreshing lock');

                $this->lock->refresh();
            } catch (LockConflictedException | LockAcquiringException $e) {
                $this->getLogger()->warning('Could not refresh lock: aborting');
                break;
            }
        }
        $this->getLogger()->info("Processing of pastoral upload completed: $success succeeded, $failed failed");
    }


    /**
     * Attempts to transmit a pending upload to the external system.
     * Returns whether the transmission was successful.
     *
     * @param Pastoral\UploadQueue\Model $pendingUpload
     * @return bool
     */
    private function processPendingUpload(Pastoral\UploadQueue\Model $pendingUpload): bool
    {
        if ($pendingUpload->getStorage() === null) {
            $this->getLogger()->error("Uploading of upload queue item '{$pendingUpload->getId()}' failed. No storage object found");
            $pendingUpload->setStatus(Pastoral\UploadQueue\Mapper::UPLOAD_STATUS_FAILED);
            $this->pastoralUploadQueueMapper->save($pendingUpload);
            return false;
        }

        $hash = $pendingUpload->getStorage()->getHash();
        $this->getLogger()->info("Starting process of file '{$hash}'");

        // Mark file as processing so another daemon get started and try to duplicate processing
        $pendingUpload->setStatus(Pastoral\UploadQueue\Mapper::UPLOAD_STATUS_PROCESSING);
        $this->pastoralUploadQueueMapper->save($pendingUpload);

        // Upload file to DocMan
        try {
            $result = $this->externalPastoral->uploadPastoralDocument($pendingUpload);
            // result will be false, when SIS is offline
            $pendingUpload->setStatus($result ? Pastoral\UploadQueue\Mapper::UPLOAD_STATUS_COMPLETED : Pastoral\UploadQueue\Mapper::UPLOAD_STATUS_PENDING);
        } catch (\InvalidArgumentException $e) {
            $this->getLogger()->warning("File could not be processed: {$e->getMessage()}", ['exception' => $e]);
            $pendingUpload->setStatus(Pastoral\UploadQueue\Mapper::UPLOAD_STATUS_FAILED);
        } catch (Exception $e) {
            $this->getLogger()->error($e, ['exception' => $e]);
            $pendingUpload->setStatus(Pastoral\UploadQueue\Mapper::UPLOAD_STATUS_FAILED);
        }
        $this->pastoralUploadQueueMapper->save($pendingUpload);

        if ($pendingUpload->getStatus() === Pastoral\UploadQueue\Mapper::UPLOAD_STATUS_FAILED) {
            $this->getLogger()->error("Uploading of file '{$hash}' failed");
            return false;
        } elseif ($pendingUpload->getStatus() === Pastoral\UploadQueue\Mapper::UPLOAD_STATUS_PENDING) {
            $this->getLogger()->warning("Skipped uploading of file '{$hash}'");
            return true;
        }
        $this->getLogger()->info("Uploading of file '{$hash}' completed");
        return true;
    }
}
