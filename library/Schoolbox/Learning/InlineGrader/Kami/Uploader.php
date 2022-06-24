<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami;

use Alaress\Database;
use Alaress\Jitter\IJitter;
use Alaress_Date_Format;
use Psr\Log\LoggerInterface;
use Schoolbox\GenericCollection;
use GuzzleHttp\Exception\ConnectException;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Sentry\State\HubInterface;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockInterface;

/**
 * Uploads eligible student submission files into Kami and inserts the
 * document's upload status into the schoolbox database.
 *
 * Does not store the view session URL, that must be generated on-demand to
 * account for route changes, and session expiry
 */
class Uploader
{
    /** @var bool */
    private $dryRun;

    /** @var IConnector */
    private $kamiConnector;

    /** @var IJitter */
    private $jitter;

    /** @var Mapper */
    private $kamiMapper;


    /**
     * @var LockInterface
     */
    private LockInterface $lock;


    /** @var LoggerInterface */
    private $log;

    /**
     * The Sentry instance for reporting errors to, if there is one.
     */
    private ?HubInterface $sentry;


    /**
     * Build the uploader.
     *
     * @param bool              $dryRun
     * @param IConnector        $kamiConnector
     * @param IJitter           $jitter
     * @param Mapper            $kamiMapper
     * @param LoggerInterface   $log
     * @param LockInterface     $lock
     * @param HubInterface|null $sentry
     * @param FilesystemProxy   $filesystemProxy
     */
    public function __construct(
        bool $dryRun,
        IConnector $kamiConnector,
        IJitter $jitter,
        Mapper $kamiMapper,
        LoggerInterface $log,
        LockInterface $lock,
        ?HubInterface $sentry,
        private FilesystemProxy $filesystemProxy,
    ) {
        $this->dryRun        = $dryRun;
        $this->kamiConnector = $kamiConnector;
        $this->jitter        = $jitter;
        $this->kamiMapper    = $kamiMapper;
        $this->log           = $log;
        $this->lock          = $lock;
        $this->sentry        = $sentry;
    }


    /**
     * Has the uploader timed out?
     *
     * In practise, this checks to see if any items have been uploading for longer than `$seconds` seconds.
     * If so, this process has timed out.
     *
     * @param int $seconds Number of seconds that must elapse before we consider an upload as timed-out.
     * @return bool
     * @throws Database\Exception if a query went wrong
     */
    public function isTimedOut(int $seconds): bool
    {
        $cutOff = Alaress_Date_Format::now()->addSeconds(-$seconds);
        $pb     = $this->kamiMapper->getPredicateBuilder();
        $pb->equals('status', Model::STATUS_UPLOADING);
        $pb->lessThan('updated_at', $cutOff->getDatabase());
        return (bool)$this->kamiMapper->countByPredicates($pb);
    }


    /**
     * Mark all kami documents that are still uploading as timed out.
     *
     * Timed-out kami documents are also marked as failed, so that `upload()`
     * with `$retryFailed` will attempt to re-process them.
     *
     * @return array|int[] Returns the IDs of affected models
     * @throws \PDOException If unable to save any models to database
     */
    public function timeout(): array
    {
        $pb = $this->kamiMapper->getPredicateBuilder();
        $pb->equals('status', Model::STATUS_UPLOADING);
        $uploadingModels = $this->kamiMapper->findByPredicates($pb);

        $this->log->warning(sprintf("Upload has timed out. Finding '%s' documents.", Model::STATUS_UPLOADING));
        if (!$uploadingModels->count()) {
            $this->log->warning(sprintf("No '%s' documents found", Model::STATUS_UPLOADING));
        }
        foreach ($uploadingModels as $kamiModel) {
            $kamiModel->setStatus(Model::STATUS_FAILED);
            $kamiModel->setErrorMessage('Uploading timed out');
            $this->log->warning(sprintf(
                "Uploading kami (id: %d) status set to '%s'",
                $kamiModel->getId(),
                Model::STATUS_FAILED
            ));
        }
        return $this->saveKamiModelCollection($uploadingModels);
    }


    /**
     * Attempts to upload all Kami documents
     *
     * @param bool          $retryFailed If true, upload 'failed', otherwise upload 'queued'
     * @param int           $batchSize   When fetching from the DB, break into batches to avoid OOM
     * @param int           $maxSkips    Successive failures may indicate an API problem.
     * @return UploadResults
     * @throws \LogicException
     * @throws \PDOException
     */
    public function upload(bool $retryFailed, int $batchSize, int $maxSkips): UploadResults
    {
        $this->log->info('Initiating upload');

        $results            = new UploadResults();
        $successiveFailures = 0;
        $kamiCount          = $this->getKamiCount($retryFailed);

        // Keep going till we upload everything, or error out
        while (true) {
            // Get the next batch (use offset if dry-run to simulate queue head being removed)
            $offset     = $this->dryRun ? $results->getCountTotal() : 0;
            $kamiModels = $this->getKamiModels($offset, $batchSize, $retryFailed);

            if (!$kamiModels->count()) {
                $this->log->notice('No kami documents left to upload.');
                break;
            }
            $this->log->notice("Processing next batch of {$kamiModels->count()} models");

            foreach ($kamiModels as $kamiModel) {
                $this->logStartingUpload($results, $kamiCount, $kamiModel);
                $kamiModel->setStatus(Model::STATUS_UPLOADING);
                $this->log->info(sprintf(
                    "Setting kami document (id: %d) status to '%s'",
                    $kamiModel->getId(),
                    Model::STATUS_UPLOADING
                ));
                $this->saveKamiModel($kamiModel);

                try {
                    $kamiDoc            = $this->uploadToKami($kamiModel);
                    $successiveFailures = 0;
                    $results->incrementSuccessCount();
                    $this->log->notice(sprintf(
                        "Upload successful. documentId: '%s'. status: '%s'",
                        $kamiDoc->getId(),
                        $kamiDoc->getFileStatus()
                    ));
                } catch (Exception\NotFound $e) {
                    //file not found, error out
                    $kamiModel->setStatus(Model::STATUS_ERROR);
                    $kamiModel->setErrorMessage($e->getMessage());
                    $this->saveKamiModel($kamiModel);
                    $this->logUploadFailure($kamiModel, $e);
                    continue;
                } catch (\Throwable $lastException) {
                    $successiveFailures++;
                    $kamiModel->setStatus(Model::STATUS_FAILED);
                    $kamiModel->setErrorMessage($lastException->getMessage());
                    $this->saveKamiModel($kamiModel);
                    $results->incrementFailureCount();
                    $this->logUploadFailure($kamiModel, $lastException);
                    $this->log->error(sprintf(
                        "Setting kami document (id: %d) status to '%s'",
                        $kamiModel->getId(),
                        Model::STATUS_FAILED
                    ));

                    // Log the fail in Sentry
                    if ($this->sentry) {
                        $this->sentry->captureException($lastException);
                    }

                    if ($lastException instanceof ConnectException) {
                        $this->log->error('Network is down: aborting!');
                        return $results;
                    }

                    if ($successiveFailures > $maxSkips) {
                        $this->log->error('Too many sequential failures: aborting!');
                        return $results;
                    }
                    continue;
                }

                $kamiModel->setKamiDocumentId($kamiDoc->getId());
                $kamiModel->setKamiCreatedAt($kamiDoc->getCreatedAt());
                $kamiModel = $this->updateKamiModelState($kamiModel, $kamiDoc);
                $this->saveKamiModel($kamiModel);

                /* Refresh lock while we're still processing; keep using another
                 * $timeout seconds until we're done, or until we died
                 */
                try {
                    $this->log->info('Refreshing lock');

                    $this->lock->refresh();
                } catch (LockConflictedException | LockAcquiringException $e) {
                    $this->log->warning('Could not refresh lock: aborting');
                    break;
                }
            }
        }
        return $results;
    }


    /**
     * Robustly attempt to upload a document to Kami
     *
     * @param Model $kamiModel
     * @return Document
     * @throws \Throwable
     */
    private function uploadToKami(Model $kamiModel): Document
    {
        $countFailedAttempts = 0;
        return $this->jitter->retry(function () use ($kamiModel, &$countFailedAttempts) {
            try {
                return $this->kamiConnector->uploadDocument(
                    $kamiModel->getStudentSubmissionFile()->getStorage(),
                    $this->filesystemProxy,
                );
            } catch (Exception\NotFound $e) {
                // file not found
                throw $e;
            } catch (Exception\IKamiException $e) {
                // If we recognise the exception, just print the info we need.
                $this->logUploadException($e, $kamiModel, $countFailedAttempts);
                throw $e;
            } catch (ConnectException $e) {
                // Network is unreachable.
                $this->logUploadException($e, $kamiModel, $countFailedAttempts);
                throw $e;
            } catch (\Throwable $e) {
                // But if unknown, also print the trace to help debugging
                $this->logUploadException($e, $kamiModel, $countFailedAttempts);
                $this->log->error($e->getTraceAsString());
                throw $e;
            } finally {
                $countFailedAttempts++;
            }
        });
    }


    /**
     * Convenience method to log an exception that occurred during upload
     *
     * @param \Throwable $e
     * @param Model      $kamiModel
     * @param int        $countFailedAttempts
     */
    private function logUploadException(\Throwable $e, Model $kamiModel, int $countFailedAttempts): void
    {
        $this->log->error(
            sprintf(
                "Upload failure. id: %d, attempt #%d, message: '%s'",
                $kamiModel->getId(),
                $countFailedAttempts,
                $e->getMessage()
            ),
            $e->getTrace()
        );
    }


    /**
     * Convenience method to log the start of an upload
     *
     * @param UploadResults $results
     * @param int           $kamiCount
     * @param Model         $kamiModel
     */
    private function logStartingUpload(UploadResults $results, int $kamiCount, Model $kamiModel): void
    {
        $this->log->notice(sprintf(
            "Uploading %d of %d. id: %d, filename: '%s'",
            $results->getCountTotal() + 1,
            $kamiCount,
            $kamiModel->getId(),
            $kamiModel->getStudentSubmissionFile()->getBasename()
        ));
    }


    /**
     * Convenience method to log a failed upload
     *
     * @param Model      $kamiModel
     * @param \Throwable $e
     */
    private function logUploadFailure(Model $kamiModel, \Throwable $e): void
    {
        $this->log->error(
            sprintf(
                "Upload failure. id: %d, message: '%s'",
                $kamiModel->getId(),
                $e->getMessage()
            ),
            $e->getTrace()
        );
    }


    /**
     * Return how many kami documents we need to process
     *
     * @param bool $retryFailed
     * @return int
     */
    private function getKamiCount(bool $retryFailed): int
    {
        $pb = $this->kamiMapper->getPredicateBuilder();
        $pb->equals('status', $retryFailed ? Model::STATUS_FAILED : Model::STATUS_QUEUED);
        $kamiCount = $this->kamiMapper->countByPredicates($pb);
        if ($retryFailed) {
            $this->log->notice(sprintf("Retrying %d '%s' uploads", $kamiCount, Model::STATUS_FAILED));
        } else {
            $this->log->notice(sprintf("Uploading %d '%s' documents", $kamiCount, Model::STATUS_QUEUED));
        }
        return $kamiCount;
    }


    /**
     * Copy the remote Kami API's state to our local model
     *
     * @param Model    $kamiModel
     * @param Document $kamiDoc
     * @return Model
     * @throws \LogicException If status is not recognised
     */
    private function updateKamiModelState(Model $kamiModel, Document $kamiDoc): Model
    {
        // For robustness, do not assume kami status string will never change. Ensure decoupled implementation.
        if ($kamiDoc->isProcessing()) {
            $kamiModel->setStatus(Model::STATUS_PROCESSING);
        } elseif ($kamiDoc->isDone()) {
            $kamiModel->setStatus(Model::STATUS_DONE);
        } elseif ($kamiDoc->isError()) {
            $kamiModel->setStatus(Model::STATUS_ERROR);
            $kamiModel->setErrorMessage($kamiDoc->getFileErrorMessage());
        } else {
            throw new \LogicException("Unknown Kami document upload status: {$kamiModel->getStatus()}");
        }
        return $kamiModel;
    }


    /**
     * Saves a Kami model (or simulates if in dryRun)
     *
     * @param Model $kamiModel
     * @return array|int[]
     * @throws \PDOException If unable to save the model
     */
    private function saveKamiModel(Model $kamiModel): array
    {
        if ($this->dryRun) {
            return [$kamiModel->getId()];
        }
        return $this->kamiMapper->save($kamiModel);
    }


    /**
     * Saves a collection of Kami models (or simulates if in dryRun)
     *
     * @param GenericCollection $models
     * @return array|int[]
     * @throws \PDOException If unable to save any of the models
     */
    private function saveKamiModelCollection(GenericCollection $models): array
    {
        if ($this->dryRun) {
            return $models->getColumnValues('id');
        }
        return $this->kamiMapper->save($models);
    }


    /**
     * Get the next batch of kami models to upload
     *
     * @param int  $offset
     * @param int  $batchSize
     * @param bool $retryFailed
     * @return GenericCollection|Model[]
     */
    private function getKamiModels(int $offset, int $batchSize, bool $retryFailed): GenericCollection
    {
        $pb = $this->kamiMapper->getPredicateBuilder();
        $pb->equals('status', $retryFailed ? Model::STATUS_FAILED : Model::STATUS_QUEUED);
        // FIFO - get oldest updated. This also ensures permanent failures are pushed to back of queue.
        $options = [
            'order'  => ['updated_at' => 'ASC'],
            'offset' => $offset,
            'limit'  => $batchSize,
        ];
        return $this->kamiMapper->findByPredicates($pb, $options);
    }
}
