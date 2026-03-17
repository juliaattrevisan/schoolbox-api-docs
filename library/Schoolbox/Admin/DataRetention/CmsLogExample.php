<?php

/**
 * example strategy, clear things from the cmsLog older than 2 years
 *
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention;

use Alaress\Database\Predicate\Builder;

class CmsLogExample extends StrategyBase
{
    private ?string $retentionPeriod = null;

    /**
     * This is an example with no db config, so set this to 365 days as an example, but we should be using the config loader to get this value from the config key defined in $retentionConfigKey
     * @inheritDoc
     */
    protected function getRetentionPeriod()
    {
        if (null === $this->retentionPeriod) {
            $this->retentionPeriod = (\Alaress_Date_Format::newNaturalDate("-365 days"))->getDatabase();
        }
        return $this->retentionPeriod;
    }

    public function setRetentionPeriod(?int $retentionPeriod): void
    {
        $this->retentionPeriod = (\Alaress_Date_Format::newNaturalDate(sprintf("-%s days", $retentionPeriod)))->getDatabase();
    }

    /**
     * @inheritDoc
     */
    public function getRetentionLabel(): string
    {
        return 'CMSLog Retention - This is an example strategy';
    }

    public function getStrategyName(): string
    {
        return 'cmslog';
    }

    protected function hasDataToDelete(): bool
    {
        // find how cmsLog Entries are stored in the database
        $query = "
            SELECT COUNT(*)
            FROM cmsLog
            WHERE time IS NOT NULL AND time < :old
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
                                'old'   => $this->getRetentionPeriod(),
                            ]);

        $count = $statement->fetchColumn();
        $this->log->info('Found ' . $count . ' logs to delete');

        return $count > 0;
    }

    /**
     * Get the ids of the logs to delete
     * @return array
     */
    protected function getIdsToDelete(): array
    {
        $this->log->info('Deleting logs older than ' . $this->getRetentionPeriod());
        $query = "
        SELECT
            id
        FROM cmsLog
        WHERE time IS NOT NULL AND time < :old
    ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'old'   => $this->getRetentionPeriod(),
        ]);

        // collate ids to delete
        $deleteIds = [];
        while ($row = $statement->fetch()) {
            $deleteIds[] = [
                'id' => $row['id'],
            ];
        }
        return $deleteIds;
    }

    protected function deleteChunk(array $chunk): void
    {
        // extract the ids from the chunk
        $chunkIds = array_column($chunk, 'id');

        // debug log the ids we are deleting in this chunk
        $this->log->debug(sprintf("Deleting cmsLog ids: %s", implode(', ', $chunkIds)));

        // if chunk is full, we can use the pre-prepared statements to save some time preparing statements
        if (count($chunkIds) === $this->chunkSize) {
            $statement = $this->prepareFullChunkQueries();
        } else {
            $statement = $this->preparePartChunkQueries($chunkIds);
        }
        // each loop needs this
        $delParams       = Builder::getArrayParameters($chunkIds, 'id');

        // do the delete
        $statement->execute($delParams);

        // if perform is true, delete cmsLogs older than the retention period
        if ($this->perform) {
            $this->log->notice(
                sprintf(
                    "Deleted '%s' cmsLogs",
                    $statement->rowCount(),
                )
            );
        } else {
            $this->log->notice(
                sprintf(
                    "Would have deleted '%s' cmsLogs",
                    $statement->rowCount(),
                )
            );
        }
    }

    /**
     * Prepare the queries we will need to delete a full chunk of logs
     * this is reused to assist with not preparing each time for multiple chunks
     * @return \PDOStatement
     */
    private function prepareFullChunkQueries(): \PDOStatement
    {
        // build an array length of the chunk size
        $chunkSize = array_fill(0, $this->chunkSize, 0);
        $delPlaceholders = Builder::getArrayPlaceholders($chunkSize, 'id');
        return $this->database->prepare($this->deleteStatement('cmsLog') . " FROM cmsLog WHERE id IN {$delPlaceholders}");
    }

    /**
     * Prepare the queries we will need to delete a chunk of logs with the specific chunk ids
     * @param array $chunkIds
     * @return \PDOStatement
     */
    private function preparePartChunkQueries(array $chunkIds): \PDOStatement
    {
        $delPlaceholders = Builder::getArrayPlaceholders($chunkIds, 'id');
        return $this->database->prepare($this->deleteStatement('cmsLog') . " FROM cmsLog WHERE id IN {$delPlaceholders}");
    }
}
