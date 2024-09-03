<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Tile;

use Alaress_Date_Format;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;

/**
 * Index builder for tiles.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * @param int|string|null $itemId
     * @inheritdoc
     */
    public function buildUpdates(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, int|string|null $itemId): iterable
    {
        // Notes:
        // * Tiles without a title should not appear in search results (AC for SA-8841)
        // * Only tiles in homepages should be included in the index (not dashboards, not templates)
        // * Tiles only in deleted folders should not be indexed
        // * Check the related query in buildDeletes() after making changes to this query.
        $queryString = "
            SELECT
                t.id,
                t.title_text,
                t.link,
                t.modified_at,
                tlf.folder_id
            FROM tile t
            JOIN tile_layout tl ON tl.id=t.tile_layout_id
            JOIN tile_layout_for_folder tlf ON tlf.tile_layout_id=tl.id
            JOIN folder f ON tlf.folder_id=f.id
            WHERE t.deleted_at IS NULL
            AND tl.deleted_at IS NULL
            AND f.deleted_at IS NULL
            AND t.title_text<>''
        ";
        $params = [];
        if ($fromDate) {
            $queryString .= ' AND t.modified_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND t.modified_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding tiles...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d tiles', $query->rowCount()));
        foreach ($query as $row) {
            $index = $this->buildIndex(
                (string)$row['id'],
                $row['title_text'],
                Hydrator::BASE_TYPE,
                $row['modified_at'],
                true,
                null,
                $row['folder_id'],
            );
            $index = $this->addFulltextIndex($index, $row['link'], self::INDEX_STRATEGY_TEXT);
            yield $index;
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate): iterable
    {
        // What database updates could cause a tile that was included
        // in the buildUpdates() query to be excluded from that query?
        //  * tl  row could be deleted or updated so it no longer links -+- No need to consider these cases because rows
        //  * tlf row could be deleted or updated so it no longer links  |  from these tables are never hard-deleted by
        //  * f   row could be deleted or updated so it no longer links /   the application or updated in such a way
        //  * tile.deleted_at        could be set to non-null               that would break the links.
        //  * tile_layout.deleted_at could be set to non-null
        //  * folder.deleted_at      could be set to non-null
        //  * tile.title_text        could be set to blank
        $queryString = "
            SELECT t.id
            FROM tile t
            JOIN tile_layout tl ON tl.id=t.tile_layout_id
            JOIN tile_layout_for_folder tlf ON tlf.tile_layout_id=tl.id
            JOIN folder f ON tlf.folder_id=f.id
            WHERE (COALESCE(t.deleted_at, tl.deleted_at, f.deleted_at) IS NOT NULL OR t.title_text='')
        ";
        $params = [];
        if ($fromDate && $toDate) {
            $queryString .= (
                ' AND (
                    t.deleted_at BETWEEN :fromDate AND :toDate OR
                    tl.deleted_at BETWEEN :fromDate AND :toDate OR
                    f.deleted_at BETWEEN :fromDate AND :toDate
                )'
            );
            $params['fromDate'] = $fromDate->getDatabase();
            $params['toDate'] = $toDate->getDatabase();
        } elseif ($fromDate) {
            $queryString .= (
                ' AND (
                    t.deleted_at >= :fromDate OR
                    tl.deleted_at >= :fromDate OR
                    f.deleted_at >= :fromDate
                )'
            );
            $params['fromDate'] = $fromDate->getDatabase();
        } elseif ($toDate) {
            $queryString .= (
                ' AND (
                    t.deleted_at <= :toDate OR
                    tl.deleted_at <= :toDate OR
                    f.deleted_at <= :toDate
                )'
            );
            $params['toDate'] = $toDate->getDatabase();
        }
        $query = $this->connector->prepareQuery($queryString);
        $query->execute($params);
        $this->logger->info(sprintf('Deleting search indices for %d tiles', $query->rowCount()));
        foreach ($query as $row) {
            yield $this->buildIndex(
                (string)$row['id'],
                null,
                Hydrator::BASE_TYPE,
                null,
                true,
                null,
                null,
            );
        }
    }
}
