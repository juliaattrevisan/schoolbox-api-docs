<?php

/**
 * My Files, retain soft deleted data for a certain time
 *
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Strategy;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Admin\DataRetention\ValidStrats;
use Schoolbox\Config\ConfigMapper;

class MyFiles extends Base
{
    protected string $retentionConfigKey = ConfigMapper::NAME_DATA_RETENTION_MYFILES;

    // DB table names (updated to snake_case)
    private const string TABLE_NAME_FILE = 'my_file';
    private const string TABLE_NAME_FOLDER = 'my_folder';

    /**
     * @inheritDoc
     */
    public function getRetentionLabel(): string
    {
        return 'My Files Retention';
    }

    /**
     * @inheritDoc
     */
    public function getStrategyName(): string
    {
        return ValidStrats::MYFILES;
    }

    /**
     * @inheritDoc
     */
    protected function hasDataToDelete(): bool
    {
        // find out how many MyFiles are soft deleted for the retention period

        $query = "
            SELECT
                count(*) AS files
            FROM " . self::TABLE_NAME_FILE . "
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
            FROM " . self::TABLE_NAME_FOLDER . "
            WHERE deleted_at IS NOT NULL AND deleted_at < :old
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
                                'old'   => $this->getRetentionPeriod(),
                            ]);

        $folderToDelete = $statement->fetch();

        // if no entries exit early
        if (empty($folderToDelete['folders']) && empty($filesToDelete['files'])) {
            $this->log->info("Found '0' MyFiles and MyFolders to delete");
            return false;
        }

        $this->log->info(sprintf("Found '%s' MyFiles to delete", $filesToDelete['files']));
        $this->log->info(sprintf("Found '%s' MyFolders to delete", $folderToDelete['folders']));
        return true;
    }

    /**
     * @inheritDoc
     */
    protected function getIdsToDelete(): array
    {
        $this->log->info('Deleting MyFiles older than ' . $this->getRetentionPeriod());
        $query = "
            SELECT
                id
            FROM " . self::TABLE_NAME_FILE . "
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
            FROM " . self::TABLE_NAME_FOLDER . "
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

    /**
     * @inheritDoc
     */
    protected function deleteChunk(array $chunk): void
    {
        /** from the chunk, extract the ids for files and folders, then delete them separately, to avoid issues with foreign key constraints
         */
        $fileIds = array_column(array_filter($chunk, fn($item) => $item['table'] === self::TABLE_NAME_FILE), 'id');
        $folderIds = array_column(array_filter($chunk, fn($item) => $item['table'] === self::TABLE_NAME_FOLDER), 'id');

        // debug log the ids we are deleting in this chunk
        $this->log->debug(sprintf("Deleting my_file ids: %s", implode(', ', $fileIds)));

        // clean up the folders if any exist...
        if (!empty($folderIds)) {
            $this->log->debug(sprintf("Deleting my_folder ids: %s", implode(', ', $folderIds)));
        }

        // Delete files and folders using the padded chunk approach to work with Builder placeholders
        // remove any eportfolio publish links that point to these myFile rows (remove the link, leave the component)
        $eportfolioDeleted = $this->deleteEportfolioLinks($fileIds);

        // Delete files before folders so folder deletions don't fail due to my_file rows referencing them
        $filesDeleted = $this->deleteFiles($fileIds);

        // Delete folders (iteratively deleting leaf folders to avoid parent FK violations)
        $foldersDeleted = $this->deleteFolders($folderIds);

        // note: transaction commit/rollback is handled by Base::process(), do not commit here

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
        // Use JOIN-style delete with an alias so joins can be added safely if needed by FK changes
        $statement = $this->database->prepare($this->deleteStatement('f') . " FROM " . self::TABLE_NAME_FILE . " AS f WHERE f.id IN {$delPlaceholders}");

        $statement->execute(Builder::getArrayParameters($fileIds, 'id'));

        return $statement->rowCount();
    }

    /**
     * delete Folders by id, returns the number of rows deleted
     *
     * @param array $folderIds
     * @return int
     */
    private function deleteFolders(array $folderIds): int
    {
        if (empty($folderIds)) {
            return 0;
        }

        // some value which will never be an ID
        $nonExistentId = -1;

        $deletedTotal = 0;

        // Work on a copy of the IDs so we can iteratively delete leaf folders (those with no children)
        $remaining = array_values($folderIds);

        while (!empty($remaining)) {
            // pad up to chunk size so Builder placeholders are consistent
            if (count($remaining) < $this->chunkSize) {
                $padded = array_merge($remaining, array_fill(0, $this->chunkSize - count($remaining), $nonExistentId));
            } else {
                $padded = $remaining;
            }

            // Find leaf folders among the remaining set (folders that have no child folders)
            $selectPlaceholders = Builder::getArrayPlaceholders($padded, 'id');
            $selectStmt = $this->database->prepare(
                "SELECT fo.id FROM " . self::TABLE_NAME_FOLDER . " AS fo LEFT JOIN " . self::TABLE_NAME_FOLDER . " AS ch ON ch.parent = fo.id WHERE ch.id IS NULL AND fo.id IN {$selectPlaceholders}"
            );
            $selectStmt->execute(Builder::getArrayParameters($padded, 'id'));

            $idsToDelete = [];
            while ($row = $selectStmt->fetch()) {
                $idsToDelete[] = $row['id'];
            }

            // Nothing deletable this iteration -> break to avoid infinite loop
            if (empty($idsToDelete)) {
                break;
            }

            // pad idsToDelete to chunk size for consistent placeholders
            if (count($idsToDelete) < $this->chunkSize) {
                $idsToDelete = array_merge($idsToDelete, array_fill(0, $this->chunkSize - count($idsToDelete), $nonExistentId));
            }

            $delPlaceholders = Builder::getArrayPlaceholders($idsToDelete, 'id');
            $delStmt = $this->database->prepare($this->deleteStatement('fo') . " FROM " . self::TABLE_NAME_FOLDER . " AS fo WHERE fo.id IN {$delPlaceholders}");

            $delStmt->execute(Builder::getArrayParameters($idsToDelete, 'id'));

            $deleted = $delStmt->rowCount();
            $deletedTotal += $deleted;

            // remove any truly deleted ids from the remaining set (ignore padded non-existent ids)
            $remaining = array_values(array_diff($remaining, array_filter($idsToDelete, fn($id) => $id !== $nonExistentId)));
        }

        if (!empty($remaining)) {
            $this->log->error(
                sprintf(
                    'Unable to delete %d folder(s); remaining IDs: %s',
                    count($remaining),
                    implode(',', $remaining)
                )
            );
        }

        return $deletedTotal;
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
        $statement = $this->database->prepare($this->deleteStatement('eportfolio_component_filelist_item') . " FROM eportfolio_component_filelist_item WHERE file_id IN {$delPlaceholders}");

        $statement->execute(Builder::getArrayParameters($fileIds, 'id'));

        return $statement->rowCount();
    }
}
