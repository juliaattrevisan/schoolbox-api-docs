#!/usr/bin/php
<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Lock\InstanceLockFactory;
use Aura\Di;
use Psr\Log\LoggerInterface;
use Schoolbox\Pastoral;
use Schoolbox\Application\CliTool;
use Symfony\Component\Lock\LockInterface;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Synergetic DocMan file uploader
 *
 * This script should be run regularly by cron, it picks up any pastoral care documents in the queue
 * and inserts them into the Synergetic DB
 */
$app = new class extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';

    /** @var Pastoral\UploadQueue\Mapper */
    private $pastoralUploadQueueMapper;

    /** @var External_Pastoral */
    private $externalPastoral;


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }

    /** @inheritDoc */
    public function setup(array $appOptions, Di\Container $di, $outStream, LoggerInterface $logger, bool $perform): CliTool\App
    {
        parent::setup($appOptions, $di, $outStream, $logger, $perform);

        // Only process if pastoral docman writeback is actually enabled in config
        if (!$this->getConfig()->get('external_pastoral_upload')) {
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
        $lock = $this->checkOverlappingExecution();
        $this->uploadPending();
        $lock->release();
    }


    /**
     * Checks whether the execution of this App is overlapping with a prior execution.
     *
     * Returns the acquired lock
     *
     * @return LockInterface
     */
    private function checkOverlappingExecution(): LockInterface
    {
        /** @var InstanceLockFactory $lockFactory */
        $lockFactory = $this->getDi()->get('lockFactory');
        $timeout     = (int)$this->getConfig()->get('pastoral_upload_timeout');
        $lock        = $lockFactory->createLock('pastoral_upload', $timeout);

//        $lock = new Alaress_Lock('pastoral_upload.' . INSTANCE_ID . '.lock');

        if ($lock->acquire()) {
            $this->setMaxExecutionTime($timeout);
            return $lock;
        }

        $this->getLogger()->critical(
            'A pastoral uploader is already running: trusting it will self-terminate on timeout'
        );
        exit(1);

//        $this->getLogger()->critical('A pastoral uploader is already running, checking timeout.');
//
//        // Check existing upload process for timeout (should never be more than uploaded more than once).
//        $timeout = (int)$this->getConfig()->get('pastoral_upload_timeout');
//        if ($timeout <= 0) {
//            $this->getLogger()->alert('Timeout of pastoral uploading is disabled.');
//            exit;
//        }
//
//        $countTimedOut = $this->pastoralUploadQueueMapper->markProcessingAsFailed($timeout);
//        if ($countTimedOut > 0) {
//            $nounAndVerb = $countTimedOut > 1 ? 'uploads have' : 'upload has';
//            $pronoun     = $countTimedOut > 1 ? 'these' : 'this';
//            $this->getLogger()->warning("Processing of $countTimedOut pastoral $nounAndVerb timed out - marking $pronoun as failed, and continuing");
//        } else {
//            $this->getLogger()->info('No pastoral uploads have timed out.');
//        }
//
//        $lock->killProcess();
//        if(!$lock->acquire()){
//            $this->getLogger()->critical('Still unable to acquire lock after attempt to kill locking process, exiting.');
//            exit(1);
//        }
//
//        return $lock;
    }


    /**
     * Find and upload pending documents to external SIS
     */
    private function uploadPending(): void
    {
        $success = $failed = 0;
        foreach ($this->pastoralUploadQueueMapper->getPending() as $pendingUpload) {
            try {
                $this->processPendingUpload($pendingUpload) ? $success++ : $failed++;
            } catch (PDOException $e) {
                $this->getLogger()->error($e->getMessage(), ['exception' => $e]);
                $failed++;
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
            $this->externalPastoral->uploadPastoralDocument($pendingUpload);
            $pendingUpload->setStatus(Pastoral\UploadQueue\Mapper::UPLOAD_STATUS_COMPLETED);
        } catch (Exception $e) {
            $this->getLogger()->error($e, ['exception' => $e]);
            $pendingUpload->setStatus(Pastoral\UploadQueue\Mapper::UPLOAD_STATUS_FAILED);
        }
        $this->pastoralUploadQueueMapper->save($pendingUpload);

        if ($pendingUpload->getStatus() === Pastoral\UploadQueue\Mapper::UPLOAD_STATUS_FAILED) {
            $this->getLogger()->error("Uploading of file '{$hash}' failed");
            return false;
        }
        $this->getLogger()->info("Uploading of file '{$hash}' completed");
        return true;
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]);
$kernel->runApp();
