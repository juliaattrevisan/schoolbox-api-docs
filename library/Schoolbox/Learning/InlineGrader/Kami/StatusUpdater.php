<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami;

use Alaress\Jitter\IJitter;
use GuzzleHttp\Exception\ConnectException;
use Psr\Log\LoggerInterface;
use LogicException;
use Sabre\DAV\Xml\Request\Lock;
use Schoolbox\Learning\InlineGrader\Kami\Exception\IKamiException;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockInterface;


/**
 * A tool for checking the status of all 'processing' kami documents
 * to determine if they have progressed to 'done' or 'failed'
 */
class StatusUpdater
{

    public const DEFAULT_MAX_ATTEMPTS = 3;           // How many times to try each document
    public const DEFAULT_MAX_SKIPS = 3;              // Number of consecutive failures that will trigger exit
    public const DEFAULT_BASE_JITTER_BACK_OFF = 500; // Number of milliseconds to wait if an attempt fails
    public const DEFAULT_TIMEOUT = 3600;             // Number of seconds after which we assume an instance has hung

    /** @var IConnector */
    private $connector;

    /** @var Mapper */
    private $mapper;

    /** @var IJitter */
    private $jitter;

    /** @var LoggerInterface */
    private $log;

    /** @var int */
    private $countSuccess;

    /** @var int */
    private $countWarning;

    /** @var int */
    private $countFailure;


    /**
     * @var LockInterface|null
     */
    private ?LockInterface $lock;


    /**
     * @param IConnector         $connector
     * @param Mapper             $mapper
     * @param IJitter            $jitter
     * @param LoggerInterface    $log
     * @param LockInterface|null $lock
     */
    public function __construct(IConnector $connector, Mapper $mapper, IJitter $jitter, LoggerInterface $log, ?LockInterface $lock = null)
    {
        $this->connector = $connector;
        $this->mapper    = $mapper;
        $this->jitter    = $jitter;
        $this->log       = $log;
        $this->lock      = $lock;
    }


    /**
     * Checks whether documents with a status of 'processing' have finished processing.
     *
     * @param bool $dryRun
     * @param int  $maxSkips
     * @return int
     * @throws IKamiException
     * @throws LogicException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \Throwable
     * @throws \UnexpectedValueException
     */
    public function checkProcessingDocumentsStatus(bool $dryRun, int $maxSkips): int
    {
        $this->resetCounts();
        $processingDocumentIds = $this->findProcessingDocumentsIds();

        $count = 1;
        $total = \count($processingDocumentIds);
        $skips = 0;
        foreach ($processingDocumentIds as $id) {
            $kamiModel = $this->mapper->findById($id);
            $this->log->info("{$count}/{$total} - Checking document id: {$id}, guid: {$kamiModel->getKamiDocumentId()}");
            $count++;
            try {
                $this->updateStatus($kamiModel, $dryRun);
                $skips = 0;
            } catch (IKamiException $e) {
                $skips++;
                if ($skips <= $maxSkips) {
                    $this->log->warning("{$skips} consecutive failure ({$maxSkips} permitted). Skipping to next document");
                } else {
                    $this->log->error("{$skips} consecutive skips. Exiting");
                    throw $e;
                }
            }

            /* Refresh lock while we're still processing; keep using another
             * $timeout seconds until we're done, or until we died
             */
            if ($this->lock) {
                try {
                    $this->log->info('Refreshing lock');

                    $this->lock->refresh();
                } catch (LockConflictedException | LockAcquiringException $e) {
                    $this->log->warning('Could not refresh lock: aborting');
                    break;
                }
            }
        }
        $this->logTotalCounts();
        return $this->countFailure ? 1 : 0;
    }


    /**
     * Reset counts to 0
     */
    private function resetCounts(): void
    {
        $this->countSuccess = $this->countWarning = $this->countFailure = 0;
    }


    /**
     * Returns the ids of all documents with a status of 'processing'
     *
     * @return int[]
     */
    private function findProcessingDocumentsIds(): array
    {
        $ids = $this->mapper->findIdsByColumn('status', Model::STATUS_PROCESSING);
        $this->log->info(sprintf("%d documents found with status of '%s'", \count($ids), Model::STATUS_PROCESSING));
        return $ids;
    }


    /**
     * Logs the total number of success, warning, and errors
     */
    private function logTotalCounts(): void
    {
        $this->log->info(sprintf(
            'Checks complete. %d success, %d warning, %d failure',
            $this->countSuccess,
            $this->countWarning,
            $this->countFailure
        ));
    }


    /**
     * Updates the Schoolbox status to match the status in Kami
     *
     * @param Model $kamiModel
     * @param bool  $dryRun
     * @throws LogicException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     * @throws \Throwable
     */
    private function updateStatus(Model $kamiModel, bool $dryRun): void
    {
        $kamiDoc = $this->getDocument($kamiModel);
        $this->log->debug("Status in kami is now '{$kamiDoc->getFileStatus()}'");
        if ($kamiDoc->isDone()) {
            $this->countSuccess++;
        } elseif ($kamiDoc->isError()) {
            $this->log->debug("File error message: '{$kamiDoc->getFileErrorMessage()}'");
        } elseif ($kamiDoc->isProcessing()) {
            $this->log->warning('This tool needs to be re-run to re-check this document');
            $this->countWarning++;
        }
        $kamiModel = $this->copyStatusFromDocumentToModel($kamiModel, $kamiDoc);
        if (!$dryRun) {
            $this->mapper->save($kamiModel);
        }
    }


    /**
     * Returns a kami document from the Kami API
     *
     * @param Model $kamiModel
     * @return Document
     * @throws \Throwable
     */
    private function getDocument(Model $kamiModel): Document
    {
        $countFailedAttempts = 0;
        $function            = function () use ($kamiModel, &$countFailedAttempts) {
            try {
                return $this->connector->getDocument($kamiModel->getKamiDocumentId());
            } catch (Exception\IKamiException | ConnectException $e) {
                // If we recognise the exception, just print the info we need.
                $this->logAttemptException($e, $kamiModel, ++$countFailedAttempts);
                throw $e;
            } catch (\Throwable $e) {
                // But if unknown, also print the trace to help debugging
                $this->logAttemptException($e, $kamiModel, ++$countFailedAttempts);
                $this->log->error($e->getTraceAsString());
                throw $e;
            }
        };
        return $this->jitter->retry($function);
    }


    /**
     * Convenience method to log an exception that occurred during an API call attempt
     *
     * @param \Throwable $e
     * @param Model      $kamiModel
     * @param int        $countFailedAttempts
     */
    private function logAttemptException(\Throwable $e, Model $kamiModel, int $countFailedAttempts): void
    {
        $this->log->error(
            sprintf(
                "Attempt failure. id: %d, attempt #%d, message: '%s'",
                $kamiModel->getId(),
                $countFailedAttempts,
                $e->getMessage()
            ),
            $e->getTrace()
        );
    }


    /**
     * Copy the remote Kami API's state to our local model
     *
     * @param Model    $kamiModel
     * @param Document $kamiDoc
     * @return Model
     * @throws LogicException If status is not recognised
     */
    private function copyStatusFromDocumentToModel(Model $kamiModel, Document $kamiDoc): Model
    {
        // For robustness, do not assume kami status string will never change. Ensure decoupled implementation.
        if ($kamiDoc->isProcessing()) {
            $kamiModel->setStatusProcessing();
        } elseif ($kamiDoc->isDone()) {
            $kamiModel->setStatusDone();
        } elseif ($kamiDoc->isError()) {
            $kamiModel->setStatusError();
            $kamiModel->setErrorMessage($kamiDoc->getFileErrorMessage());
        } else {
            throw new LogicException("Unknown Kami document upload status: {$kamiModel->getStatus()}");
        }
        return $kamiModel;
    }
}
