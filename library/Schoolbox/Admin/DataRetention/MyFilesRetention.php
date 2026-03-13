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

    public function getStrategyName(): string
    {
        return 'myFiles';
    }

    protected function hasDataToDelete(): bool
    {
        // find out how many MyFiles are soft deleted for the retention period

        $query = "
            SELECT
                count(*) AS files
            FROM myFile
            WHERE deleted_at IS NOT NULL AND deleted_at < :old
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
            WHERE deleted_at IS NOT NULL AND deleted_at < :old
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
            WHERE deleted_at IS NOT NULL AND deleted_at < :old
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
            WHERE deleted_at IS NOT NULL AND deleted_at < :old
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

        // Delete files and folders using the padded chunk approach to work with Builder placeholders
        // remove any eportfolio publish links that point to these myFile rows (remove the link, leave the component)
        $eportfolioDeleted = $this->deleteEportfolioLinks($fileIds);

        $filesDeleted = $this->deleteFiles($fileIds);
        $foldersDeleted = $this->deleteFolders($folderIds);

        // note: transaction commit/rollback is handled by StrategyBase::process(), do not commit here

        if ($this->perform) {
            $this->log->notice(
                sprintf(
                    "Deleted '%s' MyFolders, '%s' MyFiles, '%s' eportfolio_links",
                    $foldersDeleted,
                    $filesDeleted,
                    $eportfolioDeleted,
                )
            );
        } else {
            $this->log->notice(
                sprintf(
                    "Would have deleted '%s' MyFolders, '%s' MyFiles, '%s' eportfolio_links",
                    $foldersDeleted,
                    $filesDeleted,
                    $eportfolioDeleted,
                )
            );
        }
    }

    /**
     * delete files by id, returns the number of rows deleted
     *
     * @param array $fileIds
     * @return int
     */
    private function deleteFiles(array $fileIds): int
    {
        if (empty($fileIds)) {
            return 0;
        }

        // some value which will never be an ID
        $nonExistentId = -1;

        // pad up to chunk size so Builder placeholders are consistent
        if (count($fileIds) < $this->chunkSize) {
            $fileIds = array_merge($fileIds, array_fill(0, $this->chunkSize - count($fileIds), $nonExistentId));
        }

        $delPlaceholders = Builder::getArrayPlaceholders($fileIds, 'id');
        $statement = $this->database->prepare($this->deleteStatement() . " FROM myFile WHERE id IN {$delPlaceholders}");

        $statement->execute(Builder::getArrayParameters($fileIds, 'id'));

        return $statement->rowCount();
    }

    /**
     * delete Folders by id, returns the number of rows deleted
     *
     * @param array $fileIds
     * @return int
     */
    private function deleteFolders(array $fileIds): int
    {
        if (empty($fileIds)) {
            return 0;
        }

        // some value which will never be an ID
        $nonExistentId = -1;

        // pad up to chunk size so Builder placeholders are consistent
        if (count($fileIds) < $this->chunkSize) {
            $fileIds = array_merge($fileIds, array_fill(0, $this->chunkSize - count($fileIds), $nonExistentId));
        }

        $delPlaceholders = Builder::getArrayPlaceholders($fileIds, 'id');
        $statement = $this->database->prepare($this->deleteStatement() . " FROM myFolder WHERE id IN {$delPlaceholders}");

        $statement->execute(Builder::getArrayParameters($fileIds, 'id'));

        return $statement->rowCount();
    }

    /**
     * Delete eportfolio publish links that reference these myFile ids
     * This removes the link but leaves the component (empty) as requested.
     * @param array $fileIds
     * @return int
     */
    private function deleteEportfolioLinks(array $fileIds): int
    {
        if (empty($fileIds)) {
            return 0;
        }

        // some value which will never be an ID
        $nonExistentId = -1;

        // pad up to chunk size so Builder placeholders are consistent
        if (count($fileIds) < $this->chunkSize) {
            $fileIds = array_merge($fileIds, array_fill(0, $this->chunkSize - count($fileIds), $nonExistentId));
        }

        $delPlaceholders = Builder::getArrayPlaceholders($fileIds, 'id');
        $statement = $this->database->prepare($this->deleteStatement() . " FROM eportfolio_component_filelist_item WHERE file_id IN {$delPlaceholders}");

        $statement->execute(Builder::getArrayParameters($fileIds, 'id'));

        return $statement->rowCount();
    }
}
