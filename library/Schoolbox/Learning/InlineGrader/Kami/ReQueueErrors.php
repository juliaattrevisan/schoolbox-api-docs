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
use Schoolbox\Learning\InlineGrader\Kami\Exception\IKamiException;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockInterface;
use Throwable;

/**
 * A tool for re-queuing kami documents that have a status of 'error'
 */
class ReQueueErrors
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
     * @param IConnector      $connector
     * @param Mapper          $mapper
     * @param IJitter         $jitter
     * @param LoggerInterface $log
     */
    public function __construct(IConnector $connector, Mapper $mapper, IJitter $jitter, LoggerInterface $log)
    {
        $this->connector = $connector;
        $this->mapper    = $mapper;
        $this->jitter    = $jitter;
        $this->log       = $log;
    }


    /**
     * @param bool               $dryRun
     * @param int                $maxSkips
     * @param null|string        $errorMessageFilter If supplied, only re-queue documents which match this error message
     * @param LockInterface|null $lock
     * @return int
     * @throws ConnectException
     * @throws IKamiException
     * @throws Throwable
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function deleteAndReQueue(bool $dryRun, int $maxSkips, string $errorMessageFilter = null, ?LockInterface $lock = null): int
    {
        $this->resetCounts();

        $errorKamiIds = $this->getErrorKamiIds($errorMessageFilter);

        $total = \count($errorKamiIds);
        $count = 1;
        $skips = 0;
        foreach ($errorKamiIds as $kamiId) {
            $kamiModel = $this->mapper->findById($kamiId);
            $this->log->info("{$count}/{$total} Kami Document id: {$kamiId}, guid: {$kamiModel->getKamiDocumentId()}");
            try {
                $this->reQueueAndPurge($kamiModel, $dryRun);
                $this->countSuccess++;
                $skips = 0;
            } catch (IKamiException | ConnectException $e) {
                $this->log->error($e->getMessage());
                $skips++;
                if ($skips <= $maxSkips) {
                    $this->log->warning("{$skips} consecutive failure ({$maxSkips} permitted). Skipping to next document");
                } else {
                    $this->log->error("{$skips} consecutive skips. Exiting");
                    $this->countFailure++;
                    throw $e;
                }
            }
            $count++;

            /* Refresh lock while we're still processing; keep using another
             * $timeout seconds until we're done, or until we died
             */
            if ($lock) {
                try {
                    $this->log->info('Kami requeuer used more than seconds timeout; refreshing lock');

                    $lock->refresh();
                } catch (LockConflictedException | LockAcquiringException $e) {
                    $this->log->warning('Could not refresh lock: aborting');
                    break;
                }
            }
        }
        $this->log->info("Success count: {$this->countSuccess}, Warning count: {$this->countWarning}, Failure count: {$this->countFailure}");
        return $this->countFailure || $this->countWarning ? 1 : 0;
    }


    /**
     * Reset counts to 0
     */
    private function resetCounts(): void
    {
        $this->countSuccess = $this->countWarning = $this->countFailure = 0;
    }


    /**
     * Returns the IDs of all Kami documents that have a status of 'error'
     *
     * @param null|string $errorMessageFilter If supplied, only re-queue documents which match this error message
     * @return string[]
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \TypeError
     */
    private function getErrorKamiIds(?string $errorMessageFilter): array
    {
        $pb = $this->mapper->getPredicateBuilder()->equals('status', Model::STATUS_ERROR);
        if (\is_string($errorMessageFilter)) {
            $pb->equals('error_message', $errorMessageFilter);
        }
        $ids = $this->mapper->findIdsByPredicates($pb);

        $msg = sprintf("Re-queuing %d documents that with status = '%s'", \count($ids), Model::STATUS_ERROR);
        if (\is_string($errorMessageFilter)) {
            $msg .= " and 'error_message' = '{$errorMessageFilter}'";
        }

        $this->log->info($msg);
        return $ids;
    }

    /** @noinspection PhpDocRedundantThrowsInspection */
    /**
     * @param Model $kamiModel
     * @param bool  $dryRun Should we only pretend to delete and re-queue?
     * @throws Throwable If unable to guarantee document is deleted in Kami, or if unable to re-queue
     * @throws ConnectException
     */
    private function reQueueAndPurge(Model $kamiModel, bool $dryRun): void
    {
        // This should be wrapped in a DB transaction, but at time of writing that is not available.
        // We have a choice of two evils if our script dies:
        // 1. Delete in SB then Kami: We can have orphans in Kami
        // 2. Delete in Kami then SB: We can have SB kami models pointing to KamiDocs that don't exist
        // Of these, I'm going to assume #2 is preferable. In this case it is obvious and we can requeue the document.
        // #1 is hard because it's difficult to map from a kami doc to an SB instance
        // TODO Upgrade to transactions when they become available
        $this->deleteFromKami($kamiModel, $dryRun);

        $kamiModel
            ->setKamiDocumentId(null)
            ->setStatusQueued()
            ->setKamiCreatedAt(null);

        // If this save fails, we will have a SB kami model that points to a KamiDoc that no longer exists.
        // There are two ways to resolve this:
        // 1. Case by case. Simply set the kamiDocId to NULL, and status to QUEUED
        // 2. Re-execution of this script (entity will still be in 'error' state)
        if (!$dryRun) {
            $this->mapper->save($kamiModel);
        }
        $this->countSuccess++;
        $this->log->debug('Document successfully re-queued');
    }


    /**
     * Deletes this document from Kami API.
     *
     * Returns TRUE if the document no longer exists in Kami and hence we can safely hard-delete in Schoolbox
     * Throws an exception if unable to guarantee that the document is deleted
     *
     * @param Model $kamiModel
     * @param bool  $dryRun Should we only pretend to delete from Kami?
     * @return bool
     * @throws Throwable
     */
    private function deleteFromKami(Model $kamiModel, bool $dryRun): bool
    {
        $countFailedAttempts = 0;
        $function            = function () use ($kamiModel, $dryRun, &$countFailedAttempts) {
            try {
                if (!$dryRun) {
                    $this->connector->deleteDocument($kamiModel->getKamiDocumentId());
                }
                $this->log->debug('Document successfully deleted in Kami');
                return true;
            } catch (Exception\NotFound $e) {
                // Document was already deleted (ensures tool is idempotent)
                $this->log->warning('Document already deleted in Kami');
                return true;
            } catch (Exception\IKamiException | ConnectException $e) {
                // If we recognise the exception, just print the info we need.
                $this->logAttemptException($e, $kamiModel, ++$countFailedAttempts);
                throw $e;
            } catch (Throwable $e) {
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
}
