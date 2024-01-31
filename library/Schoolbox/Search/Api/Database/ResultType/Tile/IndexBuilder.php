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
     * @inheritdoc
     */
    public function buildUpdates(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate): iterable
    {
        // Note: Tiles without a title should not appear in search results (AC for SA-8841).
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
            WHERE t.deleted_at IS NULL
            AND tl.deleted_at IS NULL
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
            $index = $this->addFulltextIndex($index, $row['title_text'], self::INDEX_STRATEGY_TEXT);
            $index = $this->addFulltextIndex($index, $row['link'], self::INDEX_STRATEGY_TEXT);
            yield $index;
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate): iterable
    {
        $queryString = "SELECT
            t.id
            FROM tile t
            JOIN tile_layout tl ON tl.id=t.tile_layout_id
            WHERE COALESCE(t.deleted_at, tl.deleted_at) IS NOT NULL
        ";
        $params = [];
        if ($fromDate) {
            $queryString .= ' AND COALESCE(t.deleted_at, tl.deleted_at) >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND COALESCE(t.deleted_at, tl.deleted_at) <= :toDate';
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
