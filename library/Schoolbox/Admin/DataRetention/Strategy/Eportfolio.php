<?php

/**
 * Eportfolio - hard-delete soft deleted data older than a certain date
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Strategy;

use Alaress\Database\Predicate\Builder;
use PDO;
use Schoolbox\Admin\DataRetention\ValidStrats;
use Schoolbox\Config\ConfigMapper;

/**
 * Relationships between tables in the ePortfolio area:
 *
 *    eportfolio_page                                (has deleted_at)
 *    ├── eportfolio_component_blog                  (has deleted_at)
 *    │   ├── eportfolio_component_blog_comment      (has deleted_at)
 *    │   └── eportfolio_component_blog_image        (has deleted_at)
 *    ├── eportfolio_component_filelist_item         (has deleted_at)
 *    ├── eportfolio_permission                      (has no deleted_at)
 *    └── component_instance                         (has no deleted_at)
 *        ├── component_config_instance              (has no deleted_at)
 *        ├── eportfolio_component_advancedlist_item (has no deleted_at)
 *        ├── eportfolio_component_basiclist_item    (has no deleted_at)
 *        ├── eportfolio_component_linklist_item     (has no deleted_at)
 *        └── eportfolio_component_textbox_item      (has no deleted_at)
 *
 * Note the tree will only ever be three levels deep, so recursion isn't required here.
 */
class Eportfolio extends Base
{
    protected string $retentionConfigKey = ConfigMapper::NAME_DATA_RETENTION_EPORTFOLIO;

    /**
     * The name of the ePortfolio module.
     */
    private const string COMPONENT_MODULE_EPORTFOLIO = 'eportfolio';

    /**
     * Tables with `deleted_at` columns.
     * (Rows from these tables can be directly targeted for hard-deletion.)
     */
    public const string TABLE_EPORTFOLIO_PAGE = 'eportfolio_page';
    public const string TABLE_EPORTFOLIO_COMPONENT_BLOG = 'eportfolio_component_blog';
    public const string TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT = 'eportfolio_component_blog_comment';
    public const string TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE = 'eportfolio_component_blog_image';
    public const string TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM = 'eportfolio_component_filelist_item';

    /**
     * Tables without `deleted_at` columns.
     * (Rows from these tables will only be hard-deleted if they are ancestors of rows in tables with `deleted_at` columns.)
     */
    public const string TABLE_COMPONENT_CONFIG_INSTANCE = 'component_config_instance';
    public const string TABLE_COMPONENT_INSTANCE = 'component_instance';
    public const string TABLE_EPORTFOLIO_COMPONENT_ADVANCEDLIST_ITEM = 'eportfolio_component_advancedlist_item';
    public const string TABLE_EPORTFOLIO_COMPONENT_BASICLIST_ITEM = 'eportfolio_component_basiclist_item';
    public const string TABLE_EPORTFOLIO_COMPONENT_LINKLIST_ITEM = 'eportfolio_component_linklist_item';
    public const string TABLE_EPORTFOLIO_COMPONENT_TEXTBOX_ITEM = 'eportfolio_component_textbox_item';
    public const string TABLE_EPORTFOLIO_PERMISSION = 'eportfolio_permission';

    /**
     * @inheritDoc
     */
    public function getRetentionLabel(): string
    {
        return 'Eportfolio Retention';
    }

    /**
     * @inheritDoc
     */
    public function getStrategyName(): string
    {
        return ValidStrats::EPORTFOLIO;
    }

    /**
     * @inheritDoc
     */
    protected function hasDataToDelete(): bool
    {
        $old = $this->calculateDate();
        if ($old === null) {
            return false;
        }

        $counts = $this->fetchRows(
            // Note there is one `SELECT COUNT(*)` query for each of the five tables with `deleted_at` columns.
            // Each of the five subqueries excludes rows whose parents have already been counted as deletable.
            // Example:
            //  * The first subquery counts all TABLE_EPORTFOLIO_PAGE rows whose deleted_at date is older than the limit.
            //  * The next subquery counts all TABLE_EPORTFOLIO_COMPONENT_BLOG rows whose deleted_at date is older than
            //    the limit, but excludes rows whose parent TABLE_EPORTFOLIO_PAGE rows have already been counted.
            //
            // The point of this is to count deletable root nodes only.  Counting only deletable root nodes is preferable
            // to counting root nodes plus descendants because this counting method matches the actual unit of work.  A
            // root node is what the retention strategy schedules: “delete this page”, “delete this blog”, “delete this
            // independently deleted image”.  Descendants are implementation detail expanded inside deleteChunk() so FK
            // order and ownership rules are handled in one transaction.
            "
            SELECT
                (SELECT COUNT(*) FROM " . self::TABLE_EPORTFOLIO_PAGE . " p
                    WHERE p.deleted_at IS NOT NULL AND p.deleted_at < :old_page_root) AS " . self::TABLE_EPORTFOLIO_PAGE . ",
                (SELECT COUNT(*) FROM " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG . " b
                    INNER JOIN " . self::TABLE_EPORTFOLIO_PAGE . " p ON p.page_id = b.page_id
                    WHERE b.deleted_at IS NOT NULL AND b.deleted_at < :old_blog
                      AND (p.deleted_at IS NULL OR p.deleted_at >= :old_blog_page)) AS " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG . ",
                (SELECT COUNT(*) FROM " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT . " c
                    INNER JOIN " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG . " b ON b.id = c.blog_id
                    INNER JOIN " . self::TABLE_EPORTFOLIO_PAGE . " p ON p.page_id = b.page_id
                    WHERE c.deleted_at IS NOT NULL AND c.deleted_at < :old_comment
                      AND (b.deleted_at IS NULL OR b.deleted_at >= :old_comment_blog)
                      AND (p.deleted_at IS NULL OR p.deleted_at >= :old_comment_page)) AS " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT . ",
                (SELECT COUNT(*) FROM " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE . " i
                    INNER JOIN " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG . " b ON b.id = i.blog_id
                    INNER JOIN " . self::TABLE_EPORTFOLIO_PAGE . " p ON p.page_id = b.page_id
                    WHERE i.deleted_at IS NOT NULL AND i.deleted_at < :old_image
                      AND (b.deleted_at IS NULL OR b.deleted_at >= :old_image_blog)
                      AND (p.deleted_at IS NULL OR p.deleted_at >= :old_image_page)) AS " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE . ",
                (SELECT COUNT(*) FROM " . self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM . " f
                    LEFT JOIN " . self::TABLE_EPORTFOLIO_PAGE . " p ON p.page_id = f.page_id
                    WHERE f.deleted_at IS NOT NULL AND f.deleted_at < :old_filelist_item
                      AND (p.page_id IS NULL OR p.deleted_at IS NULL OR p.deleted_at >= :old_filelist_page)) AS " . self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM . "
        ",
            [
                'old_page_root' => $old,
                'old_blog' => $old,
                'old_blog_page' => $old,
                'old_comment' => $old,
                'old_comment_blog' => $old,
                'old_comment_page' => $old,
                'old_image' => $old,
                'old_image_blog' => $old,
                'old_image_page' => $old,
                'old_filelist_item' => $old,
                'old_filelist_page' => $old,
            ],
        )[0] ?? [];

        $hasData = false;

        foreach (
            [
                self::TABLE_EPORTFOLIO_PAGE,
                self::TABLE_EPORTFOLIO_COMPONENT_BLOG,
                self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT,
                self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE,
                self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM,
            ] as $table
        ) {
            $count = (int) ($counts[$table] ?? 0);
            $this->log->notice(
                "Found $count $table rows to delete" .
                " (counting deletable root nodes only - rows having a soft-deleted ancestor are excluded)"
            );
            $hasData = $hasData || $count > 0;
        }

        return $hasData;
    }

    /**
     * @inheritDoc
     */
    protected function getIdsToDelete(): array
    {
        $old = $this->calculateDate();
        if ($old === null) {
            return [];
        }

        $this->log->info('Deleting ePortfolio data older than ' . $old);

        return array_merge(
            $this->findEligiblePageTargets($old),
            $this->findEligibleBlogTargets($old),
            $this->findEligibleBlogCommentTargets($old),
            $this->findEligibleBlogImageTargets($old),
            $this->findEligibleFileListItemTargets($old),
        );
    }

    /**
     * Deletes a chunk of root targets and their cascade-delete descendants in a single transaction.
     *
     * @see database table tree diagram in the class docblock.
     * @param list<array{table: string, id: int|string}> $chunk
     */
    protected function deleteChunk(array $chunk): bool
    {
        $targetIds = $this->groupTargetIdsByTable($chunk);
        foreach ($targetIds as $table => $ids) {
            $this->log->debug(
                sprintf('Processing ePortfolio root target %s ids: %s', $table, implode(', ', $ids)),
            );
        }

        $pageIds = $targetIds[self::TABLE_EPORTFOLIO_PAGE] ?? [];                            // Directly targeted page ids.
        $blogIds = $targetIds[self::TABLE_EPORTFOLIO_COMPONENT_BLOG] ?? [];                  // Directly targeted blog ids.
        $blogCommentIds = $targetIds[self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT] ?? [];   // Directly targeted blog comment ids.
        $blogImageIds = $targetIds[self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE] ?? [];       // Directly targeted blog image ids.
        $fileListItemIds = $targetIds[self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM] ?? []; // Directly targeted file list item ids.

        $componentInstanceIds = $this->findComponentInstanceIdsByPageIds($pageIds); // Components that are children of directly targeted pages.
        $pageBlogIds = $this->findBlogIdsByPageIds($pageIds);                       // Blogs that are children of directly targeted pages (temporary variable).
        $pageCascadeBlogIds = array_values(array_diff($pageBlogIds, $blogIds));     // Blogs that are children of directly targeted pages, excluding directly targeted blogs.
                                                                                    // Note array_values() just ensures that keys start from 0 and are sequential, since
                                                                                    // array_diff() preserves the keys from the array being diffed.
        /** array<string, list<int>> */
        $deletedIdsByTableName = [];

        // Delete rows from all twelve tables covered by this strategy.

        $table = self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT;
        // eportfolio_page
        // └── eportfolio_component_blog
        //     └── eportfolio_component_blog_comment <- Delete rows from this table.
        // 1. Delete directly targeted rows.
        // 2. Delete rows whose parent blog      is a direct target (excluding rows deleted in step 1).
        // 3. Delete rows whose grandparent page is a direct target (excluding rows deleted in step 1).
        //    (Note the sets deleted in steps 2 and 3 cannot overlap due to array_diff() call above.)
        $this->deleteRowsByIds($deletedIdsByTableName, $table, $blogCommentIds);
        $this->deleteRowsByForeignIds($deletedIdsByTableName, $table, 'blog_id', $blogIds, $blogCommentIds);
        $this->deleteRowsByForeignIds($deletedIdsByTableName, $table, 'blog_id', $pageCascadeBlogIds, $blogCommentIds);

        $table = self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE;
        // eportfolio_page
        // └── eportfolio_component_blog
        //     └── eportfolio_component_blog_image
        // 1. Delete directly targeted rows.
        // 2. Delete rows whose parent blog      is a direct target (excluding rows deleted in step 1).
        // 3. Delete rows whose grandparent page is a direct target (excluding rows deleted in step 1).
        //    (Note the sets deleted in steps 2 and 3 cannot overlap due to array_diff() call above.)
        $this->deleteRowsByIds($deletedIdsByTableName, $table, $blogImageIds);
        $this->deleteRowsByForeignIds($deletedIdsByTableName, $table, 'blog_id', $blogIds, $blogImageIds);
        $this->deleteRowsByForeignIds($deletedIdsByTableName, $table, 'blog_id', $pageCascadeBlogIds, $blogImageIds);

        $table = self::TABLE_EPORTFOLIO_COMPONENT_BLOG;
        // eportfolio_page
        // └── eportfolio_component_blog
        // 1. Delete directly targeted rows,
        // 2. Delete rows whose parent page is a direct target (excluding rows deleted in step 1).
        $this->deleteRowsByIds($deletedIdsByTableName, $table, $blogIds);
        $this->deleteRowsByForeignIds($deletedIdsByTableName, $table, 'page_id', $pageIds, $blogIds);

        $table = self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM;
        // eportfolio_page
        // └── eportfolio_component_filelist_item
        // 1. Delete directly targeted rows.
        // 2. Delete rows whose parent page is a direct target (excluding rows deleted in step 1).
        $this->deleteRowsByIds($deletedIdsByTableName, $table, $fileListItemIds);
        $this->deleteRowsByForeignIds($deletedIdsByTableName, $table, 'page_id', $pageIds, $fileListItemIds);

        // Table eportfolio_permission (child of page, cascade-delete only).
        // (No need to exclude any rows, because this table is not directly targeted.)
        // eportfolio_page
        // └── eportfolio_permission
        $this->deleteRowsByForeignIds($deletedIdsByTableName, self::TABLE_EPORTFOLIO_PERMISSION, 'page_id', $pageIds);

        // Tables component_config_instance and list item tables (grandchildren of page, via component_instance, cascade-delete only).
        // (No need to exclude any rows, because these tables are not directly targeted.)
        // eportfolio_page
        // └── component_instance
        //     ├── component_config_instance
        //     ├── eportfolio_component_advancedlist_item
        //     ├── eportfolio_component_basiclist_item
        //     ├── eportfolio_component_linklist_item
        //     └── eportfolio_component_textbox_item
        $this->deleteRowsByForeignIds($deletedIdsByTableName, self::TABLE_COMPONENT_CONFIG_INSTANCE, 'component_instance_id', $componentInstanceIds);
        $this->deleteRowsByForeignIds($deletedIdsByTableName, self::TABLE_EPORTFOLIO_COMPONENT_ADVANCEDLIST_ITEM, 'component_instance_id', $componentInstanceIds);
        $this->deleteRowsByForeignIds($deletedIdsByTableName, self::TABLE_EPORTFOLIO_COMPONENT_BASICLIST_ITEM, 'component_instance_id', $componentInstanceIds);
        $this->deleteRowsByForeignIds($deletedIdsByTableName, self::TABLE_EPORTFOLIO_COMPONENT_LINKLIST_ITEM, 'component_instance_id', $componentInstanceIds);
        $this->deleteRowsByForeignIds($deletedIdsByTableName, self::TABLE_EPORTFOLIO_COMPONENT_TEXTBOX_ITEM, 'component_instance_id', $componentInstanceIds);

        // Table component_instance (child of page, cascade-delete only)
        // eportfolio_page
        // └── component_instance
        $this->deleteRowsByIds($deletedIdsByTableName, self::TABLE_COMPONENT_INSTANCE, $componentInstanceIds);

        // Table eportfolio_page (root)
        $this->deleteRowsByIds($deletedIdsByTableName, self::TABLE_EPORTFOLIO_PAGE, $pageIds, 'page_id');

        $this->logChunkResult($deletedIdsByTableName);

        // was anything deleted, return true, else false...
        foreach ($deletedIdsByTableName as $table => $ids) {
            if (count($ids) === 0) {
                continue;
            }

            // something was deleted, return early, we don't care how much was deleted, just something was...
            return true;
        }

        // nothing was deleted, don't count this chunk
        return false;
    }

    /**
     * Groups a flat list of deletion targets into arrays of IDs keyed by table name.
     *
     * @param list<array{table: string, id: int|string}> $chunk
     * @return array<string, list<int>>
     */
    protected function groupTargetIdsByTable(array $chunk): array
    {
        $idsByTable = [];

        foreach ($chunk as $target) {
            $id = (int) $target['id'];
            if ($id <= 0) {
                continue;
            }

            $idsByTable[$target['table']][] = $id;
        }

        foreach ($idsByTable as $table => $ids) {
            $idsByTable[$table] = array_values(array_unique($ids));
        }

        return $idsByTable;
    }

    /**
     * Returns the component_instance IDs for the given ePortfolio page IDs.
     *
     * @param list<int> $pageIds
     * @return list<int>
     */
    protected function findComponentInstanceIdsByPageIds(array $pageIds): array
    {
        if ($pageIds === []) {
            return [];
        }

        $placeholders = Builder::getArrayPlaceholders($pageIds, 'page_id');
        $statement = $this->getPreparedStatement(
            "SELECT id FROM " . self::TABLE_COMPONENT_INSTANCE . "
            WHERE module = :module AND identifier IN $placeholders"
        );
        $statement->execute(
            array_merge(
                ['module' => self::COMPONENT_MODULE_EPORTFOLIO],
                Builder::getArrayParameters($pageIds, 'page_id'),
            ),
        );

        return array_map(
            static fn(array $row): int => (int) $row['id'],
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /**
     * Returns all blog IDs belonging to the given ePortfolio page IDs.
     *
     * @param list<int> $pageIds
     * @return list<int>
     */
    protected function findBlogIdsByPageIds(array $pageIds): array
    {
        if ($pageIds === []) {
            return [];
        }

        $placeholders = Builder::getArrayPlaceholders($pageIds, 'page_id');
        $statement = $this->getPreparedStatement(
            "SELECT id FROM " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG . " WHERE page_id IN $placeholders"
        );
        $statement->execute(Builder::getArrayParameters($pageIds, 'page_id'));

        return array_map(
            static fn(array $row): int => (int) $row['id'],
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /**
     * Deletes rows from a table by primary key and records the count.
     *
     * @param array<string, list<int>> $deletedIdsByTableName
     * @param string             $table
     * @param list<int>          $ids
     * @param string             $column
     */
    protected function deleteRowsByIds(array &$deletedIdsByTableName, string $table, array $ids, string $column = 'id'): void
    {
        if ($ids === []) {
            return;
        }

        $placeholders = Builder::getArrayPlaceholders($ids, 'id');
        $statement = $this->getPreparedStatement(
            $this->deleteStatement($table) . " FROM $table WHERE $column IN $placeholders"
        );
        $statement->execute(Builder::getArrayParameters($ids, 'id'));

        $deletedIdsByTableName[$table] = array_values(
            array_unique(
                array_merge($deletedIdsByTableName[$table] ?? [], $ids),
            ),
        );
    }

    /**
     * Deletes rows from a table by foreign key and records the count, optionally excluding rows already deleted by a prior call.
     * The generated delete command will look like this:
     *     DELETE FROM $table
     *     WHERE $column IN ($ids)
     *     AND id NOT IN ($excludedIds)
     *
     * @param array<string, list<int>> $deletedIdsByTableName
     * @param string             $table       - The table whose rows should be deleted.
     * @param string             $column      - The name of the column of $table
     * @param list<int>          $ids         - These are ids for for use in $column of $table.
     * @param list<int>          $excludedIds - These are ids of $table.
     */
    protected function deleteRowsByForeignIds(
        array &$deletedIdsByTableName,
        string $table,
        string $column,
        array $ids,
        array $excludedIds = []
    ): void {
        if ($ids === []) {
            return;
        }

        $deletedIds = $this->findIdsByColumn($table, $column, $ids, $excludedIds);
        if ($deletedIds === []) {
            return;
        }

        $this->deleteRowsByIds($deletedIdsByTableName, $table, $deletedIds);
    }

    /**
     * Finds row IDs from a table by matching a column against the given values.
     *
     * @param string $table
     * @param string $column
     * @param list<int> $ids
     * @param list<int> $excludedIds
     * @return list<int>
     */
    private function findIdsByColumn(string $table, string $column, array $ids, array $excludedIds = []): array
    {
        $placeholders = Builder::getArrayPlaceholders($ids, $column);
        $parameters = Builder::getArrayParameters($ids, $column);
        $exclusionPredicate = '';

        if ($excludedIds !== []) {
            $excludedPlaceholders = Builder::getArrayPlaceholders($excludedIds, 'excluded_id');
            $exclusionPredicate = " AND id NOT IN $excludedPlaceholders";
            $parameters = array_merge($parameters, Builder::getArrayParameters($excludedIds, 'excluded_id'));
        }

        $rows = $this->fetchRows("SELECT id FROM $table WHERE $column IN $placeholders$exclusionPredicate", $parameters);
        $rowIds = array_map(static fn(array $row): int => (int) $row['id'], $rows);

        return array_values(array_unique(array_filter($rowIds, static fn(int $id): bool => $id > 0)));
    }

    /**
     * Logs the number of rows deleted (or that would be deleted) per table for the current chunk.
     *
     * @param array<string, list<int>> $deletedIdsByTableName
     */
    protected function logChunkResult(array $deletedIdsByTableName): void
    {
        foreach ($deletedIdsByTableName as $table => $ids) {
            $this->log->notice(
                ($this->perform ? 'Deleted' : 'Would have deleted') . ' ' .
                count($ids) . " rows from table '$table' (ids: " . implode(', ', $ids) . ')'
            );
        }
    }

    /**
     * Returns soft-deleted ePortfolio pages old enough to be hard-deleted.
     *
     * @return list<array{table: string, id: int|string}>
     */
    private function findEligiblePageTargets(string $old): array
    {
        return $this->fetchRows(
            "
            SELECT
                :table AS `table`,
                page_id AS id
            FROM " . self::TABLE_EPORTFOLIO_PAGE . "
            WHERE deleted_at IS NOT NULL AND deleted_at < :old
        ",
            ['table' => self::TABLE_EPORTFOLIO_PAGE, 'old' => $old],
        );
    }

    /**
     * Returns soft-deleted blogs old enough to be hard-deleted, whose parent page is not itself being deleted.
     *
     * @return list<array{table: string, id: int|string}>
     */
    private function findEligibleBlogTargets(string $old): array
    {
        return $this->fetchRows(
            "
            SELECT
                :table AS `table`,
                b.id
            FROM " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG . " b
            INNER JOIN " . self::TABLE_EPORTFOLIO_PAGE . " p ON p.page_id = b.page_id
            WHERE b.deleted_at IS NOT NULL
              AND b.deleted_at < :old_blog
              AND (p.deleted_at IS NULL OR p.deleted_at >= :old_page)
        ",
            ['table' => self::TABLE_EPORTFOLIO_COMPONENT_BLOG, 'old_blog' => $old, 'old_page' => $old],
        );
    }

    /**
     * Returns soft-deleted blog comments old enough to be hard-deleted,
     * whose parent blog and page are not themselves being deleted.
     *
     * @return list<array{table: string, id: int|string}>
     */
    private function findEligibleBlogCommentTargets(string $old): array
    {
        return $this->fetchRows(
            "
            SELECT
                :table AS `table`,
                c.id
            FROM " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT . " c
            INNER JOIN " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG . " b ON b.id = c.blog_id
            INNER JOIN " . self::TABLE_EPORTFOLIO_PAGE . " p ON p.page_id = b.page_id
            WHERE c.deleted_at IS NOT NULL
              AND c.deleted_at < :old_comment
              AND (b.deleted_at IS NULL OR b.deleted_at >= :old_blog)
              AND (p.deleted_at IS NULL OR p.deleted_at >= :old_page)
        ",
            [
                'table' => self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT,
                'old_comment' => $old,
                'old_blog' => $old,
                'old_page' => $old,
            ],
        );
    }

    /**
     * Returns soft-deleted blog images old enough to be hard-deleted,
     * whose parent blog and page are not themselves being deleted.
     *
     * @return list<array{table: string, id: int|string}>
     */
    private function findEligibleBlogImageTargets(string $old): array
    {
        return $this->fetchRows(
            "
            SELECT
                :table AS `table`,
                i.id
            FROM " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE . " i
            INNER JOIN " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG . " b ON b.id = i.blog_id
            INNER JOIN " . self::TABLE_EPORTFOLIO_PAGE . " p ON p.page_id = b.page_id
            WHERE i.deleted_at IS NOT NULL
              AND i.deleted_at < :old_image
              AND (b.deleted_at IS NULL OR b.deleted_at >= :old_blog)
              AND (p.deleted_at IS NULL OR p.deleted_at >= :old_page)
        ",
            [
                'table' => self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE,
                'old_image' => $old,
                'old_blog' => $old,
                'old_page' => $old,
            ],
        );
    }

    /**
     * Returns soft-deleted file list items old enough to be hard-deleted,
     * whose parent page (if any) is not itself being deleted.
     *
     * @return list<array{table: string, id: int|string}>
     */
    private function findEligibleFileListItemTargets(string $old): array
    {
        return $this->fetchRows(
            "
            SELECT
                :table AS `table`,
                f.id
            FROM " . self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM . " f
            LEFT JOIN " . self::TABLE_EPORTFOLIO_PAGE . " p ON p.page_id = f.page_id
            WHERE f.deleted_at IS NOT NULL
              AND f.deleted_at < :old_filelist_item
              AND (p.page_id IS NULL OR p.deleted_at IS NULL OR p.deleted_at >= :old_page)
        ",
            [
                'table' => self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM,
                'old_filelist_item' => $old,
                'old_page' => $old,
            ],
        );
    }
}
