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
use EtherpadLite\Client as EtherpadClient;
use EtherpadLite\Response as EtherpadResponse;
use Schoolbox\Admin\DataRetention\ValidStrats;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Permission\Access\UserAccess;

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
    protected function deleteChunk(array $chunk): bool
    {
        $requestedFolderIds = $this->normalizeIds(array_column($chunk, 'id'));

        // log the ID's we are wanting to delete, before we start checking how valid they are
        $this->log->debug(sprintf(
            "Processing Resources (CMS) chunk with folder IDs: %s",
            implode(', ', $requestedFolderIds),
        ));

        /**
         * Retention chunks can contain overlapping folder roots, such as a deleted
         * parent and one of its deleted descendants. Keep the delete plan keyed by
         * folder id so an accepted descendant is not duplicated, and so a later
         * blocked relation can remove that folder without disturbing unrelated
         * folders that were accepted from the same branch.
         */
        $foldersCache = [];

        /**
         * Remember every folder seen during branch expansion so descendant roots in
         * the same chunk do not expand their subtree again. The branch expansion
         * performs recursive child lookups and active-folder safety checks, so
         * repeating it for already-seen descendants is unnecessary database work.
         *
         * @var array<int, bool>
         */
        $folderBranchCache = [];

        /**
         * Expand each previously unseen branch once, then classify every folder in
         * that expanded branch as deletable or blocked. Blocked folders are excluded
         * because assessment/course relations are outside CMS retention ownership;
         * unblocked siblings and descendants can still be deleted in the same chunk.
         *
         * expandFolderCascade() also returns folders that were inspected but are
         * not safe to delete. Caching those inspected folders prevents a descendant
         * candidate from being retried after an ancestor branch was rejected for
         * active content, which would otherwise allow partial deletion of a tree
         * that has already failed the CMS retention safety checks.
         */
        foreach ($requestedFolderIds as $branchStart) {
            if (array_key_exists($branchStart, $folderBranchCache)) {
                continue;
            }

            $branch = $this->expandFolderCascade($branchStart);
            $testBranch = $branch['folderIds'];
            $blockedFolderIds = $this->findBlockedFolderIds($testBranch);
            $blockedFolders = array_fill_keys($blockedFolderIds, true);

            if (!empty($blockedFolderIds)) {
                $this->log->warning(sprintf(
                    "Excluding folder '%s' blocked Resources (CMS) folders from chunk due to excluded assessment/course relations: %s",
                    $branchStart,
                    implode(', ', $blockedFolderIds),
                ));
            }

            foreach ($branch['seenFolderIds'] as $folderId) {
                $folderBranchCache[$folderId] = false;
            }

            foreach ($testBranch as $folderId) {
                $folderBranchCache[$folderId] = !isset($blockedFolders[$folderId]);
                if (isset($blockedFolders[$folderId])) {
                    unset($foldersCache[$folderId]);
                    continue;
                }

                $foldersCache[$folderId] = $folderId;
            }
        }
        $foldersCache = array_values($foldersCache);

        // if folder cache is still empty then the chunk is empty return early (not deleting anything)
        if ($foldersCache === []) {
            $this->log->warning("Skipping Resources (CMS) chunk because all folders are blocked by excluded assessment/course relations");

            return false;
        }

        $counts = [];
        $counts['etherpad_pads'] = $this->cleanupHomepagePadsForFolders($foldersCache);

        $blogIds = $this->findIdsByFolder('blog', 'id', 'folder_id', $foldersCache);
        $counts['blog_comment'] = $this->deleteBlogComments($blogIds);
        $counts['blog_image'] = $this->deleteBlogImages($blogIds);
        $counts['blog'] = $this->deleteBlog($blogIds);

        $forumThreadIds = $this->findIdsByFolder('forumThread', 'threadID', 'categoryID', $foldersCache);
        $counts['forumPost'] = $this->deleteForumPosts($forumThreadIds);
        $counts['forumThread'] = $this->deleteForumThreads($forumThreadIds);

        $pollIds = $this->findIdsByFolder('poll', 'id', 'folder_id', $foldersCache);
        $counts['pollLog'] = $this->deletePollLogs($pollIds);
        $counts['poll'] = $this->deletePolls($pollIds);

        $wikiIds = $this->findIdsByFolder('wiki', 'wikiID', 'categoryID', $foldersCache);
        $counts['wiki_unlinked'] = $this->setWikiLatestRevisionNull($wikiIds);
        $counts['wikiRevision'] = $this->deleteWikiRevisions($wikiIds);
        $counts['wiki'] = $this->deleteWikis($wikiIds);

        $componentInstanceIds = $this->findIdsByFolder('component_instance', 'id', 'identifier', $foldersCache);
        $counts['component_config_instance'] = $this->deleteComponentConfigInstances($componentInstanceIds);
        $counts['component_instance'] = $this->deleteComponentInstances($componentInstanceIds);

        $tileLayouts = $this->findIdsByFolder('tile_layout_for_folder', 'tile_layout_id', 'folder_id', $foldersCache);
        $counts['tiles'] = $this->deleteTiles($tileLayouts);
        $counts['tileLayouts'] = $this->deleteTileLayouts($tileLayouts);
        $counts['tileLayoutsJoins'] = $this->deleteTileLayoutJoin($tileLayouts);

        $counts = array_merge($counts, $this->deleteSurveys($foldersCache));

        foreach ($this->folderOwnedTables() as $table => $column) {
            $counts[$table] = $this->deleteByFolderIds($table, $column, $foldersCache);
        }

        $counts['userAccess'] = $this->deleteHomepageUserAccess($foldersCache);
        $counts['folder'] = $this->deleteFolders($foldersCache);

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

        return true;
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
     * Expand one retained root folder to a leaf-first hard-delete order.
     *
     * Only soft-deleted descendants are followed. Active descendants must stay
     * out of the purge set so CMS retention cannot hard-delete visible child
     * folders by cascading through a retained parent.
     *
     * The return separates folders that can be deleted from folders that were
     * seen while making that decision. deleteChunk() needs both sets: folderIds
     * drives the destructive cleanup, while seenFolderIds prevents later chunk
     * candidates inside a rejected or already-expanded branch from being queried
     * and classified again.
     *
     * @param int $rootFolderId
     * @return array{folderIds: array<int, int>, seenFolderIds: array<int, int>}
     */
    protected function expandFolderCascade(int $rootFolderId): array
    {
        $this->log->debug(sprintf(
            "Expanding Resources (CMS) folder cascade for root folder %s",
            $rootFolderId,
        ));
        $pending = [
            ['id' => $rootFolderId, 'expanded' => false],
        ];
        $visited = [];
        $folderIds = [];
        $seenFolderIds = [];

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
            $seenFolderIds[$folderId] = $folderId;

            $activeChildFolderIds = $this->findActiveChildFolderIds([$folderId]);
            if ($activeChildFolderIds !== []) {
                /**
                 * A folder with active descendants rejects its whole branch for
                 * this chunk. The active ids explain why the branch was rejected;
                 * the soft-deleted child ids are also marked as seen so they are
                 * not later deleted as independent roots from the same chunk.
                 */
                foreach ($activeChildFolderIds as $activeChildFolderId) {
                    $seenFolderIds[$activeChildFolderId] = $activeChildFolderId;
                    $this->log->warning(sprintf(
                        "Skipping deletion of folder %s because it has active child folder %s",
                        $folderId,
                        $activeChildFolderId,
                    ));
                }

                foreach ($this->findSoftDeletedChildFolderIds([$folderId]) as $childFolderId) {
                    $seenFolderIds[$childFolderId] = $childFolderId;
                }

                continue;
            }

            // Re-queue the folder after its eligible children so parents are deleted last.
            $pending[] = ['id' => $folderId, 'expanded' => true];

            $childFolderIds = array_reverse($this->findSoftDeletedChildFolderIds([$folderId]));
            foreach ($childFolderIds as $childFolderId) {
                $seenFolderIds[$childFolderId] = $childFolderId;
                if (!isset($visited[$childFolderId])) {
                    $pending[] = ['id' => $childFolderId, 'expanded' => false];
                }
            }
        }

        return [
            'folderIds' => array_values($folderIds),
            'seenFolderIds' => array_values($seenFolderIds),
        ];
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
                'course_unit_specification' => 'folder_id',
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
     * Finds all Etherpad pads for a homepage.
     *
     * @param int $homepageId
     * @return array<int, array<string, mixed>>
     */
    protected function getHomepagePadRecords(int $homepageId): array
    {
        $query = "
            SELECT id, pad_id
            FROM etherpad_pad
            WHERE homepage_id = :homepageId
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
                // record if the API successfully removed the pad from redis
                $summary['padsDeleted']++;
            }

            // even if the API failed (the redis entry may already have been removed) we should remove the record from our db
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

            if ($client === false) {
                $this->log->warning('Cannot delete Etherpad pad because API client is not configured');
                return false;
            }

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

    // --------------
    // tile functions

    /**
     * when deleting a folder tiles are not recursively deleted. they are still marked as 'active'
     * Steps:
     * delete tile
     * delete tile layout for folder
     * delete tile layout
     */

    /**
     * Deletes Tiles by Tile Layout, no checks, just delete
     * @param array $tileLayouts
     * @return int Number of rows deleted
     */
    protected function deleteTiles(array $tileLayouts): int
    {
        return $this->deleteByIds('tile', 'tile_layout_id', $tileLayouts);
    }

    /**
     * Deletes tile_layout_for_folder Tile Layout, no checks, just delete
     * @param array $tileLayouts
     * @return int Number of rows deleted
     */
    protected function deleteTileLayoutJoin(array $tileLayouts): int
    {
        return $this->deleteByIds('tile_layout_for_folder', 'tile_layout_id', $tileLayouts);
    }

    /**
     * Deletes Tile Layouts, no checks, just delete
     * @param array $tileLayouts
     * @return int Number of rows deleted
     */
    protected function deleteTileLayouts(array $tileLayouts): int
    {
        return $this->deleteByIds('tile_layout', 'id', $tileLayouts);
    }

    // --------------------------
    // end tile functions

    // --------------
    // assess (survey) functions

    /**
     * surveys (assess.availability= survey (with NO assessmentID) in the assessment table).
     * we should remove all the related entries from all related assess tables (possible tables
     * assess
     * assess_answer
     * assess_attempt
     * assess_luQuestionType
     * assess_question
     * assess_question_bank
     * assess_question_category
     * assess_response
     * assess_section
     * )
     *
     * Question type, bank, and category tables are shared lookup/library data
     * rather than survey-instance data, so folder retention does not delete them.
     */

    /**
     * Deletes assess (survey), no checks, just delete
     * @param array<int, int> $folderIds
     * @return array<string, int> Number of rows deleted by table
     */
    protected function deleteSurveys(array $folderIds): array
    {
        $counts = [
            'assess_response' => 0,
            'assess_answer' => 0,
            'assess_question' => 0,
            'assess_attempt' => 0,
            'assess_section' => 0,
            'assess' => 0,
        ];

        if ($folderIds === []) {
            return $counts;
        }

        $surveyIds = $this->findSurveyIds($folderIds);
        $this->log->debug(sprintf(
            "Found '%s' assess survey records for CMS folder IDs: %s",
            count($surveyIds),
            implode(', ', $folderIds),
        ));

        if ($surveyIds === []) {
            return $counts;
        }

        $attemptIds = $this->findIdsByIds('assess_attempt', 'attempt_id', 'assess_id', $surveyIds);
        $sectionIds = $this->findIdsByIds('assess_section', 'section_id', 'assess_id', $surveyIds);
        $questionIds = $this->findIdsByIds('assess_question', 'question_id', 'section_id', $sectionIds);

        $counts['assess_response'] = $this->deleteSurveyResponses($attemptIds, $sectionIds, $questionIds);
        $counts['assess_answer'] = $this->deleteByIds('assess_answer', 'question_id', $questionIds);
        $counts['assess_question'] = $this->deleteByIds('assess_question', 'question_id', $questionIds);
        $counts['assess_attempt'] = $this->deleteByIds('assess_attempt', 'attempt_id', $attemptIds);
        $counts['assess_section'] = $this->deleteByIds('assess_section', 'section_id', $sectionIds);
        $counts['assess'] = $this->deleteByIds('assess', 'assess_id', $surveyIds);

        return $counts;
    }

    /**
     * Find legacy survey assess rows for the specified CMS folders.
     *
     * @param array<int, int> $folderIds
     * @return array<int, int>
     */
    protected function findSurveyIds(array $folderIds): array
    {
        if ($folderIds === []) {
            return [];
        }

        $placeholders = Builder::getArrayPlaceholders($folderIds, 'folder');
        $statement = $this->getPreparedStatement(
            "SELECT assess_id AS id FROM assess"
            . " WHERE category_id IN {$placeholders}"
            . " AND availability = :availability"
            . " AND assessment_id IS NULL"
        );
        $params = Builder::getArrayParameters($folderIds, 'folder');
        $params['availability'] = 'survey';
        $statement->execute($params);

        return array_map(
            static fn(array $row): int => (int)$row['id'],
            $statement->fetchAll(\PDO::FETCH_ASSOC),
        );
    }

    /**
     * Find ids from a table using another id column.
     *
     * @param string $table
     * @param string $idColumn
     * @param string $filterColumn
     * @param array<int, int> $ids
     * @return array<int, int>
     */
    protected function findIdsByIds(string $table, string $idColumn, string $filterColumn, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = Builder::getArrayPlaceholders($ids, 'id');
        $statement = $this->getPreparedStatement(
            "SELECT {$idColumn} AS id FROM {$table} WHERE {$filterColumn} IN {$placeholders}"
        );
        $statement->execute(Builder::getArrayParameters($ids, 'id'));

        return array_map(
            static fn(array $row): int => (int)$row['id'],
            $statement->fetchAll(\PDO::FETCH_ASSOC),
        );
    }

    /**
     * Delete survey responses by all survey-owned relations available.
     *
     * @param array<int, int> $attemptIds
     * @param array<int, int> $sectionIds
     * @param array<int, int> $questionIds
     * @return int Number of rows deleted
     */
    protected function deleteSurveyResponses(array $attemptIds, array $sectionIds, array $questionIds): int
    {
        $conditions = [];
        $params = [];

        if ($attemptIds !== []) {
            $conditions[] = 'attempt_id IN ' . Builder::getArrayPlaceholders($attemptIds, 'attempt');
            $params += Builder::getArrayParameters($attemptIds, 'attempt');
        }

        if ($sectionIds !== []) {
            $conditions[] = 'section_id IN ' . Builder::getArrayPlaceholders($sectionIds, 'section');
            $params += Builder::getArrayParameters($sectionIds, 'section');
        }

        if ($questionIds !== []) {
            $conditions[] = 'question_id IN ' . Builder::getArrayPlaceholders($questionIds, 'question');
            $params += Builder::getArrayParameters($questionIds, 'question');
        }

        if ($conditions === []) {
            return 0;
        }

        $statement = $this->getPreparedStatement(
            $this->deleteStatement('assess_response')
            . ' FROM assess_response WHERE '
            . implode(' OR ', $conditions)
        );
        $statement->execute($params);

        return $statement->rowCount();
    }

    // --------------------------
    // end assess (survey) functions
}
