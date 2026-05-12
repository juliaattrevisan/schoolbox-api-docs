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
            'old'   => $this->calculateDate(),
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
            'old'   => $this->calculateDate(),
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
        $this->log->info('Deleting MyFiles older than ' . $this->calculateDate());
        $filesTable = self::TABLE_NAME_FILE;
        $filesQuery = "
            SELECT
                id,
                \"{$filesTable}\" AS `table`
            FROM {$filesTable}
            WHERE deleted_at IS NOT NULL AND deleted_at < :old
        ";

        $statement = $this->database->prepare($filesQuery);
        $statement->execute([
            'old'   => $this->calculateDate(),
        ]);

        // collate ids to delete
        $fileDeleteIds = $statement->fetchAll(\PDO::FETCH_ASSOC);

        // now do the same for folders
        $foldersTable = self::TABLE_NAME_FOLDER;
        $folderQuery = "
            SELECT
                id,
                \"{$foldersTable}\" AS `table`
            FROM {$foldersTable}
            WHERE deleted_at IS NOT NULL AND deleted_at < :old
        ";

        $folderStatement = $this->database->prepare($folderQuery);
        $folderStatement->execute([
            'old'   => $this->calculateDate(),
        ]);

        // collate ids to delete
        $folderDeleteIds = $folderStatement->fetchAll(\PDO::FETCH_ASSOC);

        return array_merge($fileDeleteIds, $folderDeleteIds);
    }

    /**
     * Hard-delete the current chunk of MyFiles/MyFolders.
     *
     * Business rule for this strategy:
     * if a retained my_folder is being hard-deleted, every descendant my_folder
     * and my_file beneath that folder is also hard-deleted, regardless of the
     * descendant row's own deleted_at value.
     *
     * Files are always deleted before folders, and folders are deleted in
     * leaf-first order so self-referential foreign keys remain valid.
     *
     * @param array<int, array{id: int|string, table: string}> $chunk
     */
    protected function deleteChunk(array $chunk): bool
    {
        $directFileIds = $this->normalizeIds(
            array_column(
                array_filter($chunk, fn(array $item): bool => $item['table'] === self::TABLE_NAME_FILE),
                'id'
            )
        );
        $requestedFolderIds = $this->normalizeIds(
            array_column(
                array_filter($chunk, fn(array $item): bool => $item['table'] === self::TABLE_NAME_FOLDER),
                'id'
            )
        );

        $cascade = $this->expandFolderCascade($requestedFolderIds);
        $allFileIds = array_values(array_unique(array_merge($directFileIds, $cascade['fileIds'])));
        sort($allFileIds);

        $this->log->debug(sprintf("Deleting %s ids: %s", self::TABLE_NAME_FILE, implode(', ', $allFileIds)));

        if ($cascade['folderIds'] !== []) {
            $this->log->debug(sprintf("Deleting %s ids: %s", self::TABLE_NAME_FOLDER, implode(', ', $cascade['folderIds'])));
        }

        $eportfolioDeleted = $this->deleteEportfolioFileJoin($allFileIds);
        $filesDeleted = $this->deleteFiles($allFileIds);
        $foldersDeleted = $this->deleteFolders($cascade['folderIds']);

        if ($this->perform) {
            $this->log->notice(
                sprintf(
                    "Deleted '%s' MyFolders, '%s' MyFiles, '%s' ePortfolioFile links",
                    $foldersDeleted,
                    $filesDeleted,
                    $eportfolioDeleted,
                )
            );
        } else {
            $this->log->notice(
                sprintf(
                    "Would have deleted '%s' MyFolders, '%s' MyFiles, '%s' ePortfolioFile links",
                    $foldersDeleted,
                    $filesDeleted,
                    $eportfolioDeleted,
                )
            );
        }

        return true;
    }

    /**
     * @param array<int, int|string> $folderIds
     * @return array<int, int>
     */
    private function normalizeIds(array $folderIds): array
    {
        $normalized = array_map(static fn(int|string $id): int => (int) $id, $folderIds);
        $normalized = array_values(array_unique(array_filter($normalized, static fn(int $id): bool => $id > 0)));

        sort($normalized);

        return $normalized;
    }

    /**
     * Expand one or more requested root folders into a full delete plan.
     *
     * The returned folder ids are ordered deepest-first so callers can hard-delete
     * folders without violating my_folder.parent foreign keys.
     *
     * @param array<int, int> $rootFolderIds
     * @return array{folderIds: array<int, int>, fileIds: array<int, int>}
     */
    protected function expandFolderCascade(array $rootFolderIds): array
    {
        $pending = array_map(
            static fn(int $folderId): array => ['id' => $folderId, 'expanded' => false],
            array_reverse($this->normalizeIds($rootFolderIds))
        );
        $visitedFolders = [];
        $folderIds = [];
        $fileIds = [];

        while ($pending !== []) {
            ['id' => $folderId, 'expanded' => $expanded] = array_pop($pending);

            if ($expanded) {
                $folderIds[] = $folderId;
                continue;
            }

            if (isset($visitedFolders[$folderId])) {
                continue;
            }

            $visitedFolders[$folderId] = true;
            $fileIds = array_merge($fileIds, $this->findChildFileIds([$folderId]));
            $pending[] = ['id' => $folderId, 'expanded' => true];

            $childFolderIds = array_reverse($this->findChildFolderIds([$folderId]));
            foreach ($childFolderIds as $childFolderId) {
                if (isset($visitedFolders[$childFolderId])) {
                    continue;
                }

                $pending[] = ['id' => $childFolderId, 'expanded' => false];
            }
        }

        return [
            'folderIds' => array_values($folderIds),
            'fileIds' => array_values(array_unique(array_map('intval', $fileIds))),
        ];
    }

    /**
     * @param array<int, int> $parentIds
     * @return array<int, int>
     */
    protected function findChildFolderIds(array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        $placeholders = Builder::getArrayPlaceholders($parentIds, 'parent');
        $statement = $this->getPreparedStatement(
            "SELECT id FROM " . self::TABLE_NAME_FOLDER . " WHERE parent IN {$placeholders}"
        );
        $statement->execute(Builder::getArrayParameters($parentIds, 'parent'));

        return array_map(
            static fn(array $row): int => (int) $row['id'],
            $statement->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    /**
     * @param array<int, int> $parentIds
     * @return array<int, int>
     */
    protected function findChildFileIds(array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        $placeholders = Builder::getArrayPlaceholders($parentIds, 'parent');
        $statement = $this->getPreparedStatement(
            "SELECT id FROM " . self::TABLE_NAME_FILE . " WHERE parent IN {$placeholders}"
        );
        $statement->execute(Builder::getArrayParameters($parentIds, 'parent'));

        return array_map(
            static fn(array $row): int => (int) $row['id'],
            $statement->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    /**
     * delete files by id, returns the number of rows deleted
     *
     * @param array<int, int> $fileIds
     * @return int
     */
    protected function deleteFiles(array $fileIds): int
    {
        if (empty($fileIds)) {
            return 0;
        }

        $delPlaceholders = Builder::getArrayPlaceholders($fileIds, 'id');
        // Use JOIN-style delete with an alias so joins can be added safely if needed by FK changes
        $statement = $this->getPreparedStatement($this->deleteStatement('f') . " FROM " . self::TABLE_NAME_FILE . " AS f WHERE f.id IN {$delPlaceholders}");

        $statement->execute(Builder::getArrayParameters($fileIds, 'id'));

        return $statement->rowCount();
    }

    /**
     * delete Folders by id, returns the number of rows deleted
     *
     * @param array<int, int> $folderIds
     * @return int
     */
    protected function deleteFolders(array $folderIds): int
    {
        if (empty($folderIds)) {
            return 0;
        }

        $rowsDeleted = 0;

        foreach ($folderIds as $folderId) {
            $delPlaceholders = Builder::getArrayPlaceholders([$folderId], 'id');
            $delStmt = $this->getPreparedStatement(
                $this->deleteStatement('fo') . " FROM " . self::TABLE_NAME_FOLDER . " AS fo WHERE fo.id IN {$delPlaceholders}"
            );

            $delStmt->execute(Builder::getArrayParameters([$folderId], 'id'));
            $rowsDeleted += $delStmt->rowCount();
        }

        return $rowsDeleted;
    }

    /**
     * Delete eportfolio component file links for the given file ids, returns the number of rows deleted
     * This removes the link but leaves the component (empty) as requested.
     * ePortfolio components using this table Image, MyFiles, Image Gallery
     *
     * @param array<int, int> $fileIds
     * @return int
     */
    protected function deleteEportfolioFileJoin(array $fileIds): int
    {
        if (empty($fileIds)) {
            return 0;
        }

        $delPlaceholders = Builder::getArrayPlaceholders($fileIds, 'id');
        $statement = $this->getPreparedStatement($this->deleteStatement('eportfolio_component_filelist_item') . " FROM eportfolio_component_filelist_item WHERE file_id IN {$delPlaceholders}");

        $statement->execute(Builder::getArrayParameters($fileIds, 'id'));

        return $statement->rowCount();
    }
}
