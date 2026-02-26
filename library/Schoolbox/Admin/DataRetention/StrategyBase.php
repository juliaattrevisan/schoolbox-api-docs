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
use Alaress\Database\Predicate\Builder;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\DataRetention\IStrategy;
use Schoolbox\IConfigLoader;
use Symfony\Component\Lock\LockInterface;

class StrategyBase implements IStrategy
{
    public const CHUNK_LOOPS = 3;

    protected const CHUNK_SIZE = 2; // how many things to delete at once

    /* the config key to look up the retention period for this strategy, e.g. 'ConfigMapper::NAME_DATA_RETENTION_*'
     * planned options 1;1 Day:30;30 Days:365;365 Days and <null> (null is do NOT hard delete this)
     */
    public string $retentionConfigKey = '';
    /**
     * @inheritDoc
     */
    public function __construct(
        protected ?LoggerInterface $log,
        protected bool $perform,
        protected IConnector $database,
        protected LockInterface $lock,
        protected IConfigLoader $config,
        protected int $chunkLoops = self::CHUNK_LOOPS
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
        $chunkCount = ceil(count($deleteIds) / self::CHUNK_SIZE);
        $this->log->notice(sprintf("Deleting '%s' entries broken into '%s' chunks", count($deleteIds), $chunkCount));

        // chunk things into self::CHUNK_SIZE
        foreach (array_chunk($deleteIds, self::CHUNK_SIZE) as $chunkId => $chunk) {
            // begin transaction for the chunk
            $this->database->beginTransaction();

            try {
                // log the chunk we are working on
                $this->log->notice(sprintf("[%s/%s] Processing chunk", $chunkId + 1, $chunkCount));

                $this->deleteChunk($chunk);
            } catch (\PDOException | \Schoolbox\Mapper\Exception $e) {
                $this->database->rollBack();
                $this->log->error(sprintf("Error processing chunk: %s", $e->getMessage()));
            }

            /** if we have more chunks
             * check if we have looped through more than the count limit
             * check if have passed the time limit we can continue tomorrow
             * refresh the lock
             */
            //
            if ($chunkId < $chunkCount - 1) {
                // if we have looped through more than the count limit, stop for now and continue tomorrow
                if ($this->chunkLoops > 0 && $chunkLoop >= $this->chunkLoops) {
                    $this->log->notice(sprintf("Chunk loop limit of '%s' reached, stopping for now", $this->chunkLoops));
                    break;
                }
                $chunkLoop++;
                $this->lock->refresh();
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getRetentionLabel(): string
    {
        throw new Exception('Not implemented.');
    }

    /**
     * Get the retention period for this strategy, in database format
     * e.g. '2024-01-01 00:00:00'
     * null if no retention period is set (i.e. do not hard delete this data)
     * @return string|null
     */
    protected function getRetentionPeriod()
    {
        $retentionPeriod = (string)$this->config->get($this->retentionConfigKey); // configured by admin with planned options 1 Day, 30 Days, 365 Days, <null> (null is do NOT hard delete this)
        if ('' === $retentionPeriod) {
            $this->log->notice('No retention period set, skipping');
            return null;
        }

        return (\Alaress_Date_Format::newNaturalDate(sprintf("-%s days", $retentionPeriod)))->getDatabase();
    }

    /**
     * Find out if we have any data to delete, if not, we can exit early and save resources
     * @return bool
     */
    protected function hasDataToDelete(): bool
    {
        return false;
    }

    /**
     * Get the ids of the entities to delete, so we can delete them in chunks
     * @return array
     */
    protected function getIdsToDelete(): array
    {
        return [];
    }

    /**
     * Delete a chunk of entities and their related data,
     * this is transacted, and logged to assist with errors and debugging
     * @param array $chunk
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function deleteChunk(array $chunk): void
    {
    }
}
