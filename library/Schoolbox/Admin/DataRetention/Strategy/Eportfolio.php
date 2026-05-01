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
 * Note there are no links from child rows back up to parents or grandparents.
 * The tree will only ever be three levels deep.
 */
class Eportfolio extends Base
{
    protected string $retentionConfigKey = ConfigMapper::NAME_DATA_RETENTION_EPORTFOLIO;

    /**
     * The name of the ePortfolio module.
     */
    private const string COMPONENT_MODULE_EPORTFOLIO = 'eportfolio';

    /**
     * Direct-delete tables:
     * (These tables have `deleted_at` columns, so rows can be directly targeted for hard-deletion.)
     */
    private const string TABLE_EPORTFOLIO_PAGE = 'eportfolio_page';
    private const string TABLE_EPORTFOLIO_COMPONENT_BLOG = 'eportfolio_component_blog';
    private const string TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT = 'eportfolio_component_blog_comment';
    private const string TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE = 'eportfolio_component_blog_image';
    private const string TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM = 'eportfolio_component_filelist_item';

    /**
     * Cascade-delete tables:
     * (These tables do not have `deleted_at` columns, so rows will only be hard-deleted
     *  if they are ancestors of rows in tables with `deleted_at` columns.)
     */
    private const string TABLE_COMPONENT_CONFIG_INSTANCE = 'component_config_instance';
    private const string TABLE_COMPONENT_INSTANCE = 'component_instance';
    private const string TABLE_EPORTFOLIO_COMPONENT_ADVANCEDLIST_ITEM = 'eportfolio_component_advancedlist_item';
    private const string TABLE_EPORTFOLIO_COMPONENT_BASICLIST_ITEM = 'eportfolio_component_basiclist_item';
    private const string TABLE_EPORTFOLIO_COMPONENT_LINKLIST_ITEM = 'eportfolio_component_linklist_item';
    private const string TABLE_EPORTFOLIO_COMPONENT_TEXTBOX_ITEM = 'eportfolio_component_textbox_item';
    private const string TABLE_EPORTFOLIO_PERMISSION = 'eportfolio_permission';

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
            // Note there is one `SELECT COUNT(*)` query for each of the 'direct-delete' tables defined at the top of this class.
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

        foreach ([
            self::TABLE_EPORTFOLIO_PAGE,
            self::TABLE_EPORTFOLIO_COMPONENT_BLOG,
            self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT,
            self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE,
            self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM,
        ] as $table) {
            $count = (int) ($counts[$table] ?? 0);
            $this->log->notice(sprintf("Found %s %s rows to delete", $count, $table));
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
     * @see database table tree diagram in the class docblock.
     * @param list<array{table: string, id: int|string}> $chunk
     */
    protected function deleteChunk(array $chunk): void
    {
        $targetIds = $this->groupTargetIdsByTable($chunk);
        $this->logRootTargetIds($targetIds);

        $pageIds = $targetIds[self::TABLE_EPORTFOLIO_PAGE] ?? [];
        $blogIds = $targetIds[self::TABLE_EPORTFOLIO_COMPONENT_BLOG] ?? [];
        $blogCommentIds = $targetIds[self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT] ?? [];
        $blogImageIds = $targetIds[self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE] ?? [];
        $fileListItemIds = $targetIds[self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM] ?? [];

        $componentInstanceIds = $this->findComponentInstanceIdsByPageIds($pageIds);
        $pageBlogIds = $this->findBlogIdsByPageIds($pageIds);
        $pageCascadeBlogIds = array_values(array_diff($pageBlogIds, $blogIds));
        $deleted = [];

        $this->recordDeleted(
            $deleted,
            self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT,
            $this->deleteRowsByIds(self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT, $blogCommentIds),
        );
        $this->recordDeleted(
            $deleted,
            self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT,
            $this->deleteRowsByForeignIds(
                self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT,
                'blog_id',
                $blogIds,
                $blogCommentIds,
            ),
        );
        $this->recordDeleted(
            $deleted,
            self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT,
            $this->deleteRowsByForeignIds(
                self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT,
                'blog_id',
                $pageCascadeBlogIds,
                $blogCommentIds,
            ),
        );

        // eportfolio_component_blog_image (grandchild of page, via blog)
        $this->recordDeleted(
            $deleted,
            self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE,
            $this->deleteRowsByIds(self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE, $blogImageIds),
        );
        $this->recordDeleted(
            $deleted,
            self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE,
            $this->deleteRowsByForeignIds(
                self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE,
                'blog_id',
                $blogIds,
                $blogImageIds,
            ),
        );
        $this->recordDeleted(
            $deleted,
            self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE,
            $this->deleteRowsByForeignIds(
                self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE,
                'blog_id',
                $pageCascadeBlogIds,
                $blogImageIds,
            ),
        );

        // eportfolio_component_blog (child of page)
        $this->recordDeleted(
            $deleted,
            self::TABLE_EPORTFOLIO_COMPONENT_BLOG,
            $this->deleteRowsByIds(self::TABLE_EPORTFOLIO_COMPONENT_BLOG, $blogIds),
        );
        $this->recordDeleted(
            $deleted,
            self::TABLE_EPORTFOLIO_COMPONENT_BLOG,
            $this->deleteRowsByForeignIds(self::TABLE_EPORTFOLIO_COMPONENT_BLOG, 'page_id', $pageIds, $blogIds),
        );

        // eportfolio_component_filelist_item (child of page)
        $this->recordDeleted(
            $deleted,
            self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM,
            $this->deleteRowsByIds(self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM, $fileListItemIds),
        );
        $this->recordDeleted(
            $deleted,
            self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM,
            $this->deleteRowsByForeignIds(
                self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM,
                'page_id',
                $pageIds,
                $fileListItemIds,
            ),
        );

        // eportfolio_permission (child of page, cascade-delete only)
        $this->recordDeleted(
            $deleted,
            self::TABLE_EPORTFOLIO_PERMISSION,
            $this->deleteRowsByForeignIds(self::TABLE_EPORTFOLIO_PERMISSION, 'page_id', $pageIds),
        );

        // component_config_instance, list items (grandchildren of page, via component_instance, cascade-delete only)
        $this->recordDeleted(
            $deleted,
            self::TABLE_COMPONENT_CONFIG_INSTANCE,
            $this->deleteRowsByForeignIds(
                self::TABLE_COMPONENT_CONFIG_INSTANCE,
                'component_instance_id',
                $componentInstanceIds,
            ),
        );
        $this->recordDeleted(
            $deleted,
            self::TABLE_EPORTFOLIO_COMPONENT_ADVANCEDLIST_ITEM,
            $this->deleteRowsByForeignIds(
                self::TABLE_EPORTFOLIO_COMPONENT_ADVANCEDLIST_ITEM,
                'component_instance_id',
                $componentInstanceIds,
            ),
        );
        $this->recordDeleted(
            $deleted,
            self::TABLE_EPORTFOLIO_COMPONENT_BASICLIST_ITEM,
            $this->deleteRowsByForeignIds(
                self::TABLE_EPORTFOLIO_COMPONENT_BASICLIST_ITEM,
                'component_instance_id',
                $componentInstanceIds,
            ),
        );
        $this->recordDeleted(
            $deleted,
            self::TABLE_EPORTFOLIO_COMPONENT_LINKLIST_ITEM,
            $this->deleteRowsByForeignIds(
                self::TABLE_EPORTFOLIO_COMPONENT_LINKLIST_ITEM,
                'component_instance_id',
                $componentInstanceIds,
            ),
        );
        $this->recordDeleted(
            $deleted,
            self::TABLE_EPORTFOLIO_COMPONENT_TEXTBOX_ITEM,
            $this->deleteRowsByForeignIds(
                self::TABLE_EPORTFOLIO_COMPONENT_TEXTBOX_ITEM,
                'component_instance_id',
                $componentInstanceIds,
            ),
        );

        // component_instance (child of page, cascade-delete only)
        $this->recordDeleted(
            $deleted,
            self::TABLE_COMPONENT_INSTANCE,
            $this->deleteRowsByIds(self::TABLE_COMPONENT_INSTANCE, $componentInstanceIds),
        );

        // eportfolio_page (root)
        $this->recordDeleted(
            $deleted,
            self::TABLE_EPORTFOLIO_PAGE,
            $this->deleteRowsByIds(self::TABLE_EPORTFOLIO_PAGE, $pageIds, 'page_id'),
        );

        $this->logChunkResult($deleted);
    }

    /**
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
     * @param array<string, list<int>> $targetIds
     */
    private function logRootTargetIds(array $targetIds): void
    {
        foreach ($targetIds as $table => $ids) {
            $this->log->debug(
                sprintf('Processing ePortfolio root target %s ids: %s', $table, implode(', ', $ids)),
            );
        }
    }

    /**
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
            WHERE module = :module AND identifier IN {$placeholders}"
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
            "SELECT id FROM " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG . " WHERE page_id IN {$placeholders}"
        );
        $statement->execute(Builder::getArrayParameters($pageIds, 'page_id'));

        return array_map(
            static fn(array $row): int => (int) $row['id'],
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /**
     * @param list<int> $ids
     */
    protected function deleteRowsByIds(string $table, array $ids, string $column = 'id'): int
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
     * @param list<int> $ids
     * @param list<int> $excludedIds
     */
    protected function deleteRowsByForeignIds(string $table, string $column, array $ids, array $excludedIds = []): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = Builder::getArrayPlaceholders($ids, $column);
        $parameters = Builder::getArrayParameters($ids, $column);
        $exclusionPredicate = '';

        if ($excludedIds !== []) {
            $excludedPlaceholders = Builder::getArrayPlaceholders($excludedIds, 'excluded_id');
            $exclusionPredicate = " AND id NOT IN {$excludedPlaceholders}";
            $parameters = array_merge($parameters, Builder::getArrayParameters($excludedIds, 'excluded_id'));
        }

        $statement = $this->getPreparedStatement(
            $this->deleteStatement($table) . " FROM {$table} WHERE {$column} IN {$placeholders}{$exclusionPredicate}"
        );
        $statement->execute($parameters);

        return $statement->rowCount();
    }

    /**
     * @param array<string, int> $deleted
     */
    protected function recordDeleted(array &$deleted, string $table, int $count): void
    {
        $deleted[$table] = ($deleted[$table] ?? 0) + $count;
    }

    /**
     * @param array<string, int> $deleted
     */
    protected function logChunkResult(array $deleted): void
    {
        $prefix = $this->perform ? 'Deleted' : 'Would have deleted';

        foreach ($deleted as $table => $count) {
            $this->log->notice(sprintf("%s %s %s rows", $prefix, $count, $table));
        }
    }

    /**
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

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    protected function fetchRows(string $query, array $params): array
    {
        $statement = $this->database->prepare($query);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
