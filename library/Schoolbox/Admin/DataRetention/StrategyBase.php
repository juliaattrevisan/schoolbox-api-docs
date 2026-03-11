<?php

/**
 * Strategy Base, retain soft deleted data for a certain time
 *
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception;
use Psr\Log\LoggerInterface;
use Schoolbox\IConfigLoader;
use Symfony\Component\Lock\LockInterface;

abstract class StrategyBase implements IStrategy
{
    public const DEFAULT_CHUNK_LOOPS = 3; // how many times do we loop through the data in chunks

    public const DEFAULT_CHUNK_SIZE = 1000; // how many things to delete at once

    /* the config key to look up the retention period for this strategy, e.g. 'ConfigMapper::NAME_DATA_RETENTION_*'
     * planned options 1;1 Day:30;30 Days:365;365 Days and <null> (null is do NOT hard delete this)
     */
    public string $retentionConfigKey = '';

    /**
     * cache and store the retention period for this strategy,
     * so we don't have to keep looking it up from the config for every chunk,
     * and so we can override it with setRetentionPeriod if needed
     *
     * This should be in database format, e.g. '2024-01-01 00:00:00'
     * @var null|string
     */
    private ?string $retentionPeriod = null;

    /**
     * @param null|LoggerInterface $log        // log to STDERR
     * @param bool                 $perform    // should we actually perform the deletion?
     * @param IConnector           $database   // database connector, do we can craft manual queries rather than use mappers
     * @param LockInterface        $lock       // lock to prevent multiple instances of this running at once, should be refreshed by long running processes
     * @param IConfigLoader        $config     // config loader gives the strategy ability to access its own config
     * @param int                  $endTime    // at what time should we just stop processing, to prevent the tool from running for too long, should be a unix timestamp, e.g. time() + 60*60 for 1 hour from now
     * @param int                  $chunkLoops // how many times to loop through the data in chunks, default 3, set to 1 to only loop once, set to 0 for infinite looping until no more data is found
     * @param int                  $chunkSize  // how many records to process at once, default 1000
     */
    public function __construct(
        protected ?LoggerInterface $log,
        protected bool $perform,
        protected IConnector $database,
        protected LockInterface $lock,
        protected IConfigLoader $config,
        protected int $endTime,
        protected int $chunkLoops = self::DEFAULT_CHUNK_LOOPS,
        protected int $chunkSize = self::DEFAULT_CHUNK_SIZE,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function process(): void
    {
        $deleteOlderThan = $this->getRetentionPeriod();
        if ($deleteOlderThan === null) {
            return;
        }

        $this->lock->refresh();
        // init the chunk loop
        $chunkLoop = 1; // first loop

        if (!$this->hasDataToDelete()) {
            // if there is no data to delete, log and return early to avoid unnecessary processing
            $this->log->notice('No entries to delete');
        }

        // fetch ids to delete
        $deleteIds = $this->getIdsToDelete();


        // log how many chunks we will delete
        $chunkCount = ceil(count($deleteIds) / $this->chunkSize);
        $this->log->notice(sprintf("Deleting '%s' entries broken into '%s' chunks", count($deleteIds), $chunkCount));

        // chunk things into $this->chunkSize
        foreach (array_chunk($deleteIds, $this->chunkSize) as $chunkNum => $chunk) {
            // capture time, so we know how long a chunk took
            $start = microtime(true);
            // begin transaction for the chunk
            $this->database->beginTransaction();

            try {
                // log the chunk we are working on
                $this->log->notice(sprintf("[%s/%s] Processing chunk", $chunkNum + 1, $chunkCount));

                $this->deleteChunk($chunk);

                if ($this->perform) {
                    // perform the transaction for the chunk
                    $this->database->commit();
                } else {
                    // if not performing, roll back the transaction to avoid making changes
                    $this->database->rollBack();
                }
            } catch (\PDOException | \Schoolbox\Mapper\Exception $e) {
                $this->database->rollBack();
                $this->log->error(sprintf("Error processing chunk: %s", $e->getMessage()));
                $this->log->debug($e->getTraceAsString());
            }

            // log how long the chunk took to process
            $duration = microtime(true) - $start;
            $this->log->notice(sprintf("[%s/%s] Finished chunk in %ds", $chunkNum + 1, $chunkCount, $duration));

            /** if we have more chunks
             * check if we have passed the time limit we can continue tomorrow
             * check if we have looped through more than the count limit
             * refresh the lock
             */
            //
            if ($chunkNum < $chunkCount - 1) {
                // if we have passed the end time, stop for now and continue tomorrow
                if (time() > $this->endTime) {
                    $this->log->notice(sprintf("End time of '%s' reached, stopping for now", date('Y-m-d H:i:s', $this->endTime)));
                    return;
                }
                // if we have looped through more than the count limit, stop for now and continue tomorrow
                if ($this->chunkLoops > 0 && $chunkLoop >= $this->chunkLoops) {
                    $this->log->notice(sprintf("Chunk loop limit of '%s' reached, stopping for now", $this->chunkLoops));
                    return;
                }
                $chunkLoop++;
                $this->lock->refresh();
            }
        }
    }

    /**
     * @inheritDoc
     */
    abstract public function getRetentionLabel(): string;

    /**
     * Get the retention period for this strategy, in database format
     * e.g. '2024-01-01 00:00:00'
     * null if no retention period is set (i.e. do not hard delete this data)
     * @return string|null
     */
    protected function getRetentionPeriod()
    {
        if (null === $this->retentionPeriod) {
            $retentionPeriod = (string)$this->config->get($this->retentionConfigKey); // configured by admin with planned options 1 Day, 30 Days, 365 Days, <null> (null is do NOT hard delete this)
            if ('' === $retentionPeriod) {
                $this->log->notice('No retention period set, skipping');
                return null;
            }
            $retentionDate = \Alaress_Date_Format::newNaturalDate(sprintf("-%s days", $retentionPeriod));

            // if in the future for some reason, log and return null to avoid deleting everything
            if ($retentionDate->isFuture()) {
                $this->log->notice('Negative retention period, skipping');
                return null;
            }
            $this->retentionPeriod = $retentionDate->getDatabase();
        }
        return $this->retentionPeriod;
    }

    public function setRetentionPeriod(?int $retentionPeriod): void
    {
        $this->retentionPeriod = (\Alaress_Date_Format::newNaturalDate(sprintf("-%s days", $retentionPeriod)))->getDatabase();
    }

    /**
     * Find out if we have any data to delete, if not, we can exit early and save resources
     * @return bool
     */
    abstract protected function hasDataToDelete(): bool;

    /**
     * Get the ids of the entities to delete, so we can delete them in chunks
     * @return array
     */
    abstract protected function getIdsToDelete(): array;

    /**
     * Delete a chunk of entities and their related data,
     * this is transacted, and logged to assist with errors and debugging
     * @param array $chunk
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     */
    abstract protected function deleteChunk(array $chunk): void;

    /**
     * Get the strategy name, this is used to identify the strategy in the command line tool, e.g. 'cmslog'
     * @return string
     */
    abstract public function getStrategyName(): string;

    /**
     * Get the delete statement to use, rather than rely exclusively on rollback
     * @return string
     */
    protected function deleteStatement(): string
    {
        if (!$this->perform) {
            return 'SELECT';
        }
        return 'DELETE';
    }
}
