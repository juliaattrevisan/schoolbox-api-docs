<?php

/**
 * Eportfolio - retain soft deleted data for a certain time
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Strategy;

use PDO;
use Schoolbox\Admin\DataRetention\ValidStrats;
use Schoolbox\Config\ConfigMapper;

class Eportfolio extends Base
{
    protected string $retentionConfigKey = ConfigMapper::NAME_DATA_RETENTION_EPORTFOLIO;

    private const string TABLE_EPORTFOLIO_PAGE = 'eportfolio_page';
    /** @phpstan-ignore classConstant.unused (Used by later ePortfolio retention deletion tasks.) */
    private const string TABLE_EPORTFOLIO_PERMISSION = 'eportfolio_permission';
    private const string TABLE_EPORTFOLIO_COMPONENT_BLOG = 'eportfolio_component_blog';
    private const string TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT = 'eportfolio_component_blog_comment';
    private const string TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE = 'eportfolio_component_blog_image';
    private const string TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM = 'eportfolio_component_filelist_item';
    /** @phpstan-ignore classConstant.unused (Used by later ePortfolio retention deletion tasks.) */
    private const string TABLE_COMPONENT_INSTANCE = 'component_instance';
    /** @phpstan-ignore classConstant.unused (Used by later ePortfolio retention deletion tasks.) */
    private const string TABLE_COMPONENT_CONFIG_INSTANCE = 'component_config_instance';
    /** @phpstan-ignore classConstant.unused (Used by later ePortfolio retention deletion tasks.) */
    private const string TABLE_EPORTFOLIO_COMPONENT_ADVANCEDLIST_ITEM = 'eportfolio_component_advancedlist_item';
    /** @phpstan-ignore classConstant.unused (Used by later ePortfolio retention deletion tasks.) */
    private const string TABLE_EPORTFOLIO_COMPONENT_BASICLIST_ITEM = 'eportfolio_component_basiclist_item';
    /** @phpstan-ignore classConstant.unused (Used by later ePortfolio retention deletion tasks.) */
    private const string TABLE_EPORTFOLIO_COMPONENT_LINKLIST_ITEM = 'eportfolio_component_linklist_item';
    /** @phpstan-ignore classConstant.unused (Used by later ePortfolio retention deletion tasks.) */
    private const string TABLE_EPORTFOLIO_COMPONENT_TEXTBOX_ITEM = 'eportfolio_component_textbox_item';
    /** @phpstan-ignore classConstant.unused (Used by later ePortfolio retention deletion tasks.) */
    private const string COMPONENT_MODULE_EPORTFOLIO = 'eportfolio';

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
        return false;
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
     *
     */
    protected function deleteChunk(array $chunk): void
    {
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
