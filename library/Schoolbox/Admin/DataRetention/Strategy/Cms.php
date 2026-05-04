<?php

/**
 * Resources (CMS), retain soft deleted data for a certain time
 *
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Strategy;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Connector\IConnector;
use EtherpadLite\Client as EtherpadClient;
use EtherpadLite\Response as EtherpadResponse;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\DataRetention\RetentionPeriod;
use Schoolbox\Admin\DataRetention\ValidStrats;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Permission\Access\UserAccess;
use Symfony\Component\Lock\LockInterface;

class Cms extends Base
{
    protected string $retentionConfigKey = ConfigMapper::NAME_DATA_RETENTION_CMS;

    private const string TABLE_FOLDER = 'folder';

    // cache this, so we don't keep needing to re create this connection for every pad we want to delete
    private ?EtherpadClient $etherpadClient = null;

    /**
     * @inheritDoc
     */
    public function getRetentionLabel(): string
    {
        return 'Resources (CMS) Retention';
    }

    /**
     * @inheritDoc
     */
    public function getStrategyName(): string
    {
        return ValidStrats::CMS;
    }

    // ---------------
    // inherit functions

    /**
     * @inheritDoc
     */
    protected function hasDataToDelete(): bool
    {
        $query = "
            SELECT COUNT(*) AS folders
            FROM " . self::TABLE_FOLDER . "
            WHERE deleted_at IS NOT NULL AND deleted_at < :old
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'old' => $this->calculateDate(),
        ]);

        $toDelete = $statement->fetch();
        if (empty($toDelete['folders'])) {
            $this->log->info("Found '0' Resources (CMS) folders to delete");

            return false;
        }

        $this->log->info(sprintf("Found '%s' Resources (CMS) folders to delete", $toDelete['folders']));

        return true;
    }

    /**
     * Load soft-deleted CMS folder ids that are old enough and safe to hard-delete.
     *
     * A retained folder is not safe while it still has active direct children,
     * because deleting it would also remove children that have not been
     * soft-deleted.
     *
     * @return array<int, array{id: int|string}>
     */
    protected function getIdsToDelete(): array
    {
        $this->log->info('Deleting Resources (CMS) folders older than ' . $this->calculateDate());

        $query = "
            SELECT id
            FROM " . self::TABLE_FOLDER . "
            WHERE deleted_at IS NOT NULL AND deleted_at < :old
                -- Keep active child folders in place; their parent can be retried after the children are deleted.
                AND NOT EXISTS (
                    SELECT 1
                    FROM " . self::TABLE_FOLDER . " child
                    WHERE child.parent = " . self::TABLE_FOLDER . ".id
                        AND child.deleted_at IS NULL
                )
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'old' => $this->calculateDate(),
        ]);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @inheritDoc
     */
    protected function deleteChunk(array $chunk): void
    {
        $requestedFolderIds = $this->normalizeIds(array_column($chunk, 'id'));

        // log the ID's we are wanting to delete, before we start checking how valid they are
        $this->log->debug(sprintf(
            "Processing Resources (CMS) chunk with folder IDs: %s",
            implode(', ', $requestedFolderIds),
        ));

        $folderIds = $this->expandFolderCascade($requestedFolderIds);
        $blockedFolderIds = $this->findBlockedFolderIds($folderIds);

        if ($blockedFolderIds !== []) {
            $this->log->warning(sprintf(
                "Skipping Resources (CMS) chunk because '%s' folders are referenced by excluded assessment/course relations: %s",
                count($blockedFolderIds),
                implode(', ', $blockedFolderIds),
            ));

            return;
        }

        $counts = [];
        $counts['etherpad_pads'] = $this->cleanupHomepagePadsForFolders($folderIds);

        $blogIds = $this->findIdsByFolder('blog', 'id', 'folder_id', $folderIds);
        $forumThreadIds = $this->findIdsByFolder('forumThread', 'threadID', 'categoryID', $folderIds);
        $pollIds = $this->findIdsByFolder('poll', 'id', 'folder_id', $folderIds);
        $wikiIds = $this->findIdsByFolder('wiki', 'wikiID', 'categoryID', $folderIds);
        $componentInstanceIds = $this->findIdsByFolder('component_instance', 'id', 'identifier', $folderIds);

        $counts['blog_comment'] = $this->deleteBlogComments($blogIds);
        $counts['blog_image'] = $this->deleteBlogImages($blogIds);
        $counts['blog'] = $this->deleteBlog($blogIds);
        $counts['forumPost'] = $this->deleteForumPosts($forumThreadIds);
        $counts['forumThread'] = $this->deleteForumThreads($forumThreadIds);
        $counts['pollLog'] = $this->deletePollLogs($pollIds);
        $counts['poll'] = $this->deletePolls($pollIds);
        $counts['wiki_unlinked'] = $this->setWikiLatestRevisionNull($wikiIds);
        $counts['wikiRevision'] = $this->deleteWikiRevisions($wikiIds);
        $counts['wiki'] = $this->deleteWikis($wikiIds);
        $counts['component_config_instance'] = $this->deleteComponentConfigInstances($componentInstanceIds);
        $counts['component_instance'] = $this->deleteComponentInstances($componentInstanceIds);
        $counts['folder_owned_rows'] = $this->deleteFolderOwnedRows($folderIds);
        $counts['userAccess'] = $this->deleteHomepageUserAccess($folderIds);
        $counts['folder'] = $this->deleteFolders($folderIds);

        $prefix = $this->perform ? 'Deleted' : 'Would have deleted';
        $this->log->notice(sprintf(
            "%s Resources (CMS) rows: %s",
            $prefix,
            implode(', ', array_map(
                static fn(string $table, int $count): string => sprintf("%s=%s", $table, $count),
                array_keys($counts),
                $counts,
            )),
        ));
    }

    // ------------
    // end inherit

    // ------------
    // start helper functions

    /**
     * Delete homepage user access rows for the given folder ids
     * @param array<int, int> $folderIds
     * @return int Number of rows deleted
     */
    protected function deleteHomepageUserAccess(array $folderIds): int
    {
        if ($folderIds === []) {
            return 0;
        }

        $placeholders = Builder::getArrayPlaceholders($folderIds, 'folder');
        $statement = $this->getPreparedStatement(
            $this->deleteStatement('userAccess')
            . " FROM userAccess WHERE service = :service AND sid IN {$placeholders}"
        );
        $params = Builder::getArrayParameters($folderIds, 'folder');
        $params['service'] = UserAccess::SERVICE_HOMEPAGE;
        $statement->execute($params);

        return $statement->rowCount();
    }

    /**
     * Delete rows in various tables that reference the deleted folders, based on the map in
     * @see self::folderOwnedTables()
     * @param array<int, int> $folderIds
     * @return int Number of rows deleted
     */
    protected function deleteFolderOwnedRows(array $folderIds): int
    {
        $count = 0;
        foreach ($this->folderOwnedTables() as $table => $column) {
            $count += $this->deleteByFolderIds($table, $column, $folderIds);
        }

        return $count;
    }

    /**
     * Delete folders by id, without any additional checks or cascading
     * @param array<int, int> $folderIds
     * @return int Number of rows deleted
     */
    protected function deleteFolders(array $folderIds): int
    {
        $rowsDeleted = 0;
        foreach ($folderIds as $folderId) {
            $rowsDeleted += $this->deleteByIds(self::TABLE_FOLDER, 'id', [$folderId]);
        }

        return $rowsDeleted;
    }

    /**
     * Map of tables with foreign keys into folder ids, for feature-specific cleanup during folder deletion.
     * @return array<string, string>
     */
    private function folderOwnedTables(): array
    {
        return [
            'chat' => 'category_id',
            'chat_poll' => 'category_id',
            'etherpad_pad' => 'homepage_id',
            'event_folder' => 'folder_id',
            'file' => 'category',
            'folder_code' => 'folder_id',
            'folder_year_level' => 'folder_id',
            'google_file' => 'folder_id',
            'homepage_config' => 'cid',
            'homepage_log' => 'folder_id',
            'homepage_visits' => 'category_id',
            'link' => 'category',
            'moment_folder' => 'folder_id',
            'perms' => 'cid',
            'reportcard_comment' => 'category_id',
            'search_index' => 'folder_id',
            'socialstream_post' => 'homepage_id',
            'textbox' => 'folder_id',
            'tile_layout_for_folder' => 'folder_id',
            'userGroups' => 'cid',
        ];
    }

    /**
     * Canonicalise ids before building SQL placeholders or delete plans.
     *
     * Retention chunks may contain duplicate or string-cast ids from PDO. This
     * keeps later cascade and blocker checks deterministic, which matters for
     * predictable logging and leaf-first deletion order.
     *
     * @param array<int, int|string> $ids
     * @return array<int, int>
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = array_map(static fn(int|string $id): int => (int) $id, $ids);
        $normalized = array_values(array_unique(array_filter($normalized, static fn(int $id): bool => $id > 0)));

        sort($normalized);

        return $normalized;
    }

    /**
     * For a given table, delete rows by id with a single query, using the provided column and id list to build placeholders.
     * @param string          $table
     * @param string          $column
     * @param array<int, int> $ids
     * @return int
     */
    protected function deleteByIds(string $table, string $column, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = Builder::getArrayPlaceholders($ids, 'id');
        $statement = $this->getPreparedStatement(
            $this->deleteStatement($table) . " FROM {$table} WHERE {$column} IN {$placeholders}"
        );
        $statement->execute(Builder::getArrayParameters($ids, 'id'));

        return $statement->rowCount();
    }

    /**
     * For a given table, delete entries that are for the specified folder (based on $column)
     * @param string $table
     * @param string $column
     * @param array  $folderIds
     * @return int
     */
    protected function deleteByFolderIds(string $table, string $column, array $folderIds): int
    {
        if ($folderIds === []) {
            return 0;
        }

        $placeholders = Builder::getArrayPlaceholders($folderIds, 'folder');
        $statement = $this->getPreparedStatement(
            $this->deleteStatement($table) . " FROM {$table} WHERE {$column} IN {$placeholders}"
        );
        $statement->execute(Builder::getArrayParameters($folderIds, 'folder'));

        return $statement->rowCount();
    }

    //---------------
    // end helper functions

    // ---------------
    // start tree functions

    /**
     * Expand retained root folders to a leaf-first hard-delete order.
     *
     * Only soft-deleted descendants are followed. Active descendants must stay
     * out of the purge set so CMS retention cannot hard-delete visible child
     * folders by cascading through a retained parent.
     *
     * @param array<int, int> $rootFolderIds
     * @return array<int, int>
     */
    protected function expandFolderCascade(array $rootFolderIds): array
    {
        $pending = array_map(
            static fn(int $folderId): array => ['id' => $folderId, 'expanded' => false],
            array_reverse($this->normalizeIds($rootFolderIds)),
        );
        $visited = [];
        $folderIds = [];

        while ($pending !== []) {
            ['id' => $folderId, 'expanded' => $expanded] = array_pop($pending);

            if ($expanded) {
                $folderIds[] = $folderId;
                continue;
            }

            if (isset($visited[$folderId])) {
                continue;
            }

            $visited[$folderId] = true;

            $activeChildFolderIds = $this->findActiveChildFolderIds([$folderId]);
            if ($activeChildFolderIds !== []) {
                foreach ($activeChildFolderIds as $activeChildFolderId) {
                    $this->log->warning(sprintf(
                        "Skipping deletion of folder %s because it has active child folder %s",
                        $folderId,
                        $activeChildFolderId,
                    ));
                }

                continue;
            }

            // Re-queue the folder after its eligible children so parents are deleted last.
            $pending[] = ['id' => $folderId, 'expanded' => true];

            $childFolderIds = array_reverse($this->findSoftDeletedChildFolderIds([$folderId]));
            foreach ($childFolderIds as $childFolderId) {
                if (!isset($visited[$childFolderId])) {
                    $pending[] = ['id' => $childFolderId, 'expanded' => false];
                }
            }
        }

        return array_values($folderIds);
    }


    /**
     * Find every direct child folder for callers that need the actual tree.
     *
     * This deliberately does not filter by deletion state. Some safety checks
     * need to know whether active children exist, while the hard-delete cascade
     * uses self::findSoftDeletedChildFolderIds() to avoid deleting those active
     * children.
     *
     * @see self::findSoftDeletedChildFolderIds()
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
            "SELECT id FROM " . self::TABLE_FOLDER . " WHERE parent IN {$placeholders}"
        );
        $statement->execute(Builder::getArrayParameters($parentIds, 'parent'));

        return array_map(
            static fn(array $row): int => (int) $row['id'],
            $statement->fetchAll(\PDO::FETCH_ASSOC),
        );
    }

    /**
     * Find only child folders that have already been soft-deleted.
     *
     * CMS retention must be bottom-up: active children are still valid content,
     * so they must not be pulled into a hard-delete just because an ancestor is
     * past retention. This helper is the boundary that enforces that rule during
     * cascade expansion.
     *
     * @param array<int, int> $parentIds
     * @return array<int, int>
     */
    protected function findSoftDeletedChildFolderIds(array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        $pending = $this->normalizeIds($parentIds);
        $visited = [];
        $softDeletedFolderIds = [];

        while ($pending !== []) {
            $placeholders = Builder::getArrayPlaceholders($pending, 'parent');
            $statement = $this->getPreparedStatement(
                "SELECT id FROM " . self::TABLE_FOLDER . " WHERE parent IN {$placeholders} AND deleted_at IS NOT NULL"
            );
            $statement->execute(Builder::getArrayParameters($pending, 'parent'));

            $pending = [];
            foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $folderId = (int)$row['id'];
                if (isset($visited[$folderId])) {
                    continue;
                }

                $visited[$folderId] = true;
                $softDeletedFolderIds[$folderId] = $folderId;
                $pending[] = $folderId;
            }
        }

        return array_values($softDeletedFolderIds);
    }

    /**
     * Find only active (non-deleted) descendant folders.
     *
     * This helper allows cascade expansion to reject any parent that still has
     * visible child folders anywhere in its descendant tree.
     *
     * @param array<int, int> $parentIds
     * @return array<int, int>
     */
    protected function findActiveChildFolderIds(array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        $pending = $this->normalizeIds($parentIds);
        $visited = [];
        $activeFolderIds = [];

        while ($pending !== []) {
            $placeholders = Builder::getArrayPlaceholders($pending, 'parent');
            $statement = $this->getPreparedStatement(
                "SELECT id, deleted_at FROM " . self::TABLE_FOLDER . " WHERE parent IN {$placeholders}"
            );
            $statement->execute(Builder::getArrayParameters($pending, 'parent'));

            $pending = [];
            foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $folderId = (int)$row['id'];
                if (isset($visited[$folderId])) {
                    continue;
                }

                $visited[$folderId] = true;
                $pending[] = $folderId;

                if ($row['deleted_at'] === null) {
                    $activeFolderIds[$folderId] = $folderId;
                }
            }
        }

        return array_values($activeFolderIds);
    }

    /**
     * Find folders that belong to out-of-scope assessment/course relationships.
     *
     * These relations still hold foreign keys into folder, but deleting their
     * domain data is not part of CMS retention. Detecting them up front lets the
     * strategy skip the chunk intentionally instead of relying on a database
     * constraint failure part way through the transaction.
     *
     * @param array<int, int> $folderIds
     * @return array<int, int>
     */
    protected function findBlockedFolderIds(array $folderIds): array
    {
        if ($folderIds === []) {
            return [];
        }

        $blocked = [];
        $placeholders = Builder::getArrayPlaceholders($folderIds, 'folder');
        $params = Builder::getArrayParameters($folderIds, 'folder');

        foreach (
            [
                'assessment_folder' => 'folder_id',
                'course_implementation' => 'folder_id',
                'course_specification' => 'folder_id',
                'mv_assessment_relation' => 'folder_id',
            ] as $table => $column
        ) {
            $statement = $this->getPreparedStatement(
                "SELECT DISTINCT {$column} AS folder_id FROM {$table} WHERE {$column} IN {$placeholders}"
            );
            $statement->execute($params);
            $blocked = array_merge(
                $blocked,
                array_map(
                    static fn(array $row): int => (int) $row['folder_id'],
                    $statement->fetchAll(\PDO::FETCH_ASSOC),
                )
            );
        }

        return $this->normalizeIds($blocked);
    }

    /**
     * Resolve feature-specific row ids owned by the folder purge set.
     *
     * The CMS tables use different id and folder column names. Keeping this
     * lookup explicit at each call site makes the delete order reviewable while
     * avoiding repetitive placeholder code.
     *
     * @param string $table
     * @param string $idColumn
     * @param string $folderColumn
     * @param array  $folderIds
     * @return array<int, int>
     */
    protected function findIdsByFolder(string $table, string $idColumn, string $folderColumn, array $folderIds): array
    {
        if ($folderIds === []) {
            return [];
        }

        $componentInstanceHomepage = \Component_Instance::MODULE_HOMEPAGE;

        $placeholders = Builder::getArrayPlaceholders($folderIds, 'folder');
        $statement = $this->getPreparedStatement(
            sprintf(
                'SELECT %s AS id FROM %s WHERE %s IN %s%s',
                $idColumn,
                $table,
                $folderColumn,
                $placeholders,
                $table === 'component_instance' ? " AND module = '$componentInstanceHomepage'" : '',
            )
        );
        $statement->execute(Builder::getArrayParameters($folderIds, 'folder'));

        return array_map(
            static fn(array $row): int => (int) $row['id'],
            $statement->fetchAll(\PDO::FETCH_ASSOC),
        );
    }

    // --------------
    // end tree functions

    // --------------
    // wiki functions

    /**
     * Sets wiki revisions to NUll, due to foreign key constraints,
     * we need to do this before we can delete the revisions and wikis. No checks, just update.
     *
     * @param array $ids
     * @return int Number of rows updated
     */
    protected function setWikiLatestRevisionNull(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = Builder::getArrayPlaceholders($ids, 'id');
        $statement = $this->getPreparedStatement(
            ($this->perform ? 'UPDATE wiki SET latestRevision = NULL' : 'SELECT wiki.* FROM wiki')
            . " WHERE wikiID IN {$placeholders}"
        );
        $statement->execute(Builder::getArrayParameters($ids, 'id'));

        return $statement->rowCount();
    }

    /**
     * Deletes wiki revisions by wikiID, no checks, just delete
     * @param array $ids
     * @return int Number of rows deleted
     */
    protected function deleteWikiRevisions(array $ids): int
    {
        return $this->deleteByIds('wikiRevision', 'wikiID', $ids);
    }

    /**
     * Deletes wikis by wikiID, no checks, just delete
     * @param array $ids
     * @return int Number of rows deleted
     */
    protected function deleteWikis(array $ids): int
    {
        return $this->deleteByIds('wiki', 'wikiID', $ids);
    }

    // --------------------------
    // end wiki functions

    // ---------------
    // blog functions

    /**
     * Deletes blog comments identified by blog ID, no checks, just delete
     * @param array $ids
     * @return int Number of rows deleted
     */
    protected function deleteBlogComments(array $ids): int
    {
        return $this->deleteByIds('blog_comment', 'blog_id', $ids);
    }

    /**
     * Deletes blog images identified by blog ID, no checks, just delete
     * @param array $ids
     * @return int Number of rows deleted
     */
    protected function deleteBlogImages(array $ids): int
    {
        return $this->deleteByIds('blog_image', 'blog_id', $ids);
    }

    /**
     * Deletes blogs identified by blog ID, no checks, just delete
     * @param array $ids
     * @return int Number of rows deleted
     */
    protected function deleteBlog(array $ids): int
    {
        return $this->deleteByIds('blog', 'id', $ids);
    }

    // ---------------
    // end blog

    // ---------------
    // forum functions

    /**
     * Deletes Forum Posts identified by thread ID, no checks, just delete
     * @param array $ids
     * @return int Number of rows deleted
     */
    protected function deleteForumPosts(array $ids): int
    {
        return $this->deleteByIds('forumPost', 'threadID', $ids);
    }

    /**
     * Deletes Forum Threads identified by thread ID, no checks, just delete
     * @param array $ids
     * @return int Number of rows deleted
     */
    protected function deleteForumThreads(array $ids): int
    {
        return $this->deleteByIds('forumThread', 'threadID', $ids);
    }

    // ---------------
    // end forum

    // ---------------
    // poll functions

    /**
     * Deletes Poll Logs identified by poll ID, no checks, just delete
     * @param array $ids
     * @return int Number of rows deleted
     */
    protected function deletePollLogs(array $ids): int
    {
        return $this->deleteByIds('pollLog', 'poll_id', $ids);
    }

    /**
     * Deletes polls identified by poll ID, no checks, just delete
     * @param array $ids
     * @return int Number of rows deleted
     */
    protected function deletePolls(array $ids): int
    {
        return $this->deleteByIds('poll', 'id', $ids);
    }

    // ---------------
    // end poll

    // ---------------
    // component functions

    /**
     * Deletes component configs identified by component instance ID, no checks, just delete
     * @param array $ids
     * @return int Number of rows deleted
     */
    protected function deleteComponentConfigInstances(array $ids): int
    {
        if (count($ids)) {
            // log the component types we are deleting... for extra debug info
            $query = $this->database->prepare(
                "
            select
                component_instance.id,
                component_definition.name,
                component_definition.class
            from component_definition
                join component_instance on component_definition.id = component_instance.component_definition_id
            where component_instance.id in " . Builder::getArrayPlaceholders($ids, 'id')
            );

            // log the component info
            $query->execute(Builder::getArrayParameters($ids, 'id'));
            while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
                $this->log->debug(
                    sprintf(
                        "Deleting component instance '%s' with name '%s' and class '%s'",
                        $row['id'],
                        $row['name'],
                        $row['class'],
                    )
                );
            }
        } else {
            $this->log->debug("no components on folder");
        }

        return $this->deleteByIds('component_config_instance', 'component_instance_id', $ids);
    }

    /**
     * Deletes components identified by component instance ID, no checks, just delete
     * @param array $ids
     * @return int Number of rows deleted
     */
    protected function deleteComponentInstances(array $ids): int
    {
        return $this->deleteByIds('component_instance', 'id', $ids);
    }

    // -----------
    // end components

    //---------------------------
    // Etherpad functions

    /**
     * Finds all active Etherpad pads for a homepage.
     *
     * @param int $homepageId
     * @return array<int, array<string, mixed>>
     */
    protected function getHomepagePadRecords(int $homepageId): array
    {
        $query = "
            SELECT id, pad_id
            FROM etherpad_pad
            WHERE homepage_id = :homepageId AND deleted_at IS NULL
        ";

        $statement = $this->getPreparedStatement($query);
        $statement->execute(['homepageId' => $homepageId]);

        return $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Perform Etherpad pad cleanup for the specified homepage.
     *
     * @param int  $homepageId
     * @return array<string, int|bool>
     */
    public function cleanupHomepagePads(int $homepageId): array
    {
        $padRows = $this->getHomepagePadRecords($homepageId);
        $this->log->info(sprintf("Found '%s' Etherpad pads for homepage %s", count($padRows), $homepageId));

        $summary = [
            'homepageId' => $homepageId,
            'padsFound' => count($padRows),
            'padsDeleted' => 0,
            'redisKeysDeleted' => 0,
            'dbRowsDeleted' => 0,
        ];

        foreach ($padRows as $padRow) {
            $padId       = (string)($padRow['pad_id'] ?? '');
            $padRecordId = isset($padRow['id']) ? (int)$padRow['id'] : 0;

            if ($padId === '' || $padRecordId <= 0) {
                $this->log->warning(sprintf('Skipping malformed Etherpad pad record for homepage %s: %s', $homepageId, json_encode($padRow)));
                continue;
            }

            $padDeleted = $this->deletePadFromEtherpad($padId);
            if ($padDeleted) {
                $summary['padsDeleted']++;
            }

            $summary['dbRowsDeleted'] += $this->hardDeleteEtherpadPad($padRecordId);
        }

        return $summary;
    }

    /**
     * Delete an Etherpad pad via the EtherpadLite API.
     *
     * @param string $padId
     * @return bool True if the pad was successfully deleted or did not exist, false if an error occurred during deletion
     */
    protected function deletePadFromEtherpad(string $padId): bool
    {
        try {
            $client = $this->getEtherpadClient();
            if ($this->perform) {
                $response = $client->deletePad($padId);
            } else {
                $response = $client->getRevisionsCount($padId);
            }

            $code = $response->getCode();
            if ($code !== null && (string)$code === (string)EtherpadResponse::CODE_OK) {
                $this->log->notice(sprintf("Deleted Etherpad pad '%s'", $padId));
                return true;
            }

            $this->log->warning(sprintf("Etherpad API returned unexpected code '%s' for pad '%s': %s", $code, $padId, $response->getMessage()));
            return false;
        } catch (\Throwable $e) {
            $this->log->warning(sprintf("Failed to delete Etherpad pad '%s': %s", $padId, $e->getMessage()));
            return false;
        }
    }

    /**
     * Hard-delete a local Etherpad metadata row.
     * @param int $id
     * @return int Number of rows deleted
     */
    protected function hardDeleteEtherpadPad(int $id): int
    {
        $statement = $this->getPreparedStatement(
            $this->deleteStatement('etherpad_pad') . " FROM etherpad_pad WHERE id = :id"
        );
        $statement->execute(['id' => $id]);

        return $statement->rowCount();
    }

    /**
     * Clean homepage Etherpad data before folder-owned rows are deleted.
     *
     * Etherpad pads are discovered from homepage ids, which are represented by
     * CMS folder ids. This wrapper keeps that homepage-specific cleanup inside
     * the same folder purge plan and returns a count for the chunk summary.
     *
     * @param array $folderIds
     * @return int Number of pads deleted
     */
    protected function cleanupHomepagePadsForFolders(array $folderIds): int
    {
        $totalPads = 0;

        foreach ($folderIds as $folderId) {
            $this->log->debug(sprintf(
                "Homepage Etherpad cleanup for homepage %s",
                $folderId,
            ));

            $summary = $this->cleanupHomepagePads($folderId);
            $totalPads += (int)$summary['padsFound'];
        }
        return $totalPads;
    }

    /** Get a cached Etherpad client if credentials are configured, or false if not.
     *
     * @return EtherpadClient|false
     */
    private function getEtherpadClient(): EtherpadClient|false
    {
        if (null === $this->etherpadClient) {
            $apiToken = (string)$this->config->get('etherpad_api_token');
            $etherpadHost = (string)$this->config->get(ConfigMapper::NAME_ETHERPAD_HOST);

            if ($apiToken === '' || $etherpadHost === '') {
                $this->log->warning('Etherpad API credentials are not configured; cannot delete pads');
                return false;
            }
            $this->etherpadClient = new EtherpadClient($apiToken, $etherpadHost);
        }
        return $this->etherpadClient;
    }

    //---------------------------
    // end etherpad
}
