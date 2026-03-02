<?php

/**
 * My Files, retain soft deleted data for a certain time
 *
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Config\ConfigMapper;

class MyFilesRetention extends StrategyBase
{
    public string $retentionConfigKey = ConfigMapper::NAME_DATA_RETENTION_MYFILES;

    private const TABLE_NAME_FILE = 'myFile';
    private const TABLE_NAME_FOLDER = 'myFolder';

    public function getRetentionLabel(): string
    {
        return 'My Files Retention';
    }

    protected function hasDataToDelete(): bool
    {
        // find out how many MyFiles are soft deleted for the retention period

        $query = "
            SELECT
                count(*) AS files
            FROM myFile
            WHERE deleted_at < :old
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
                                'old'   => $this->getRetentionPeriod(),
                            ]);

        $filesToDelete = $statement->fetch();

        $query = "
            SELECT
                count(*) AS folders
            FROM myFolder
            WHERE deleted_at < :old
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
                                'old'   => $this->getRetentionPeriod(),
                            ]);

        $folderToDelete = $statement->fetch();

        // if no entries exit early
        if (empty($folderToDelete['folders']) && empty($filesToDelete['files'])) {
            return false;
        }

        $this->log->info(sprintf("Found '%s' MyFiles to delete", $filesToDelete['files']));
        $this->log->info(sprintf("Found '%s' MyFolders to delete", $folderToDelete['folders']));
        return true;
    }

    protected function getIdsToDelete(): array
    {
        $this->log->info('Deleting MyFiles older than ' . $this->getRetentionPeriod());
        $query = "
            SELECT
                id
            FROM myFile
            WHERE deleted_at < :old
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
                'table' => self::TABLE_NAME_FILE,
            ];
        }

        // now do the same for folders
        $folderQuery = "
            SELECT
                id
            FROM myFolder
            WHERE deleted_at < :old
        ";

        $folderStatement = $this->database->prepare($folderQuery);
        $folderStatement->execute([
                                'old'   => $this->getRetentionPeriod(),
                            ]);

        // collate ids to delete
        while ($row = $folderStatement->fetch()) {
            $deleteIds[] = [
                'id' => $row['id'],
                'table' => self::TABLE_NAME_FOLDER,
            ];
        }

        return $deleteIds;
    }

    protected function deleteChunk(array $chunk): void
    {
        /** from the chunk, extract the ids for files and folders, then delete them separately, to avoid issues with foreign key constraints
         */
        $fileIds = array_column(array_filter($chunk, fn($item) => $item['table'] === self::TABLE_NAME_FILE), 'id');
        $folderIds = array_column(array_filter($chunk, fn($item) => $item['table'] === self::TABLE_NAME_FOLDER), 'id');

        // debug log the ids we are deleting in this chunk
        $this->log->debug(sprintf("Deleting myFile ids: %s", implode(', ', $fileIds)));

        // clean up the folders if any exist...
        if (!empty($folderIds)) {
            $this->log->debug(sprintf("Deleting myFolder ids: %s", implode(', ', $folderIds)));
        }

        // each loop needs this
        $fileParams     = Builder::getArrayParameters($fileIds, 'id');

        $folderParams   = Builder::getArrayParameters($folderIds, 'id');

        // prepare for full chunk, or part chunk (for each table)

        // delete

        // log outputs
    }
}
