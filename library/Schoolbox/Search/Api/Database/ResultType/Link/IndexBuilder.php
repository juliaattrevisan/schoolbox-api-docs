<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Link;

use Alaress_Date_Format;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;

/**
 * Index builder for links.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * @param int|string|null $itemId
     * @inheritdoc
     */
    public function buildUpdates(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, int|string|null $itemId): iterable
    {
        $queryString = "
            SELECT
                l.id,
                l.url,
                l.description AS title,
                l.modified AS date,
                l.owner AS created_by,
                l.category AS folder_id,
                l.comment AS fulltext_index
            FROM link l
            INNER JOIN folder fo ON l.category = fo.id
            WHERE l.deleted_at IS NULL
            AND fo.deleted_at IS NULL
        ";
        $params = [];
        if ($fromDate) {
            $queryString .= ' AND l.modified >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND l.modified <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding links...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d links', $query->rowCount()));
        foreach ($query as $row) {
            $index = $this->buildIndex(
                (string)$row['id'],
                $row['title'],
                Hydrator::BASE_TYPE,
                $row['date'],
                true,
                $row['created_by'],
                $row['folder_id'],
            );
            $index = $this->addFulltextIndex($index, $row['url'], self::INDEX_STRATEGY_TEXT);
            $index = $this->addFulltextIndex($index, $row['fulltext_index'], self::INDEX_STRATEGY_TEXT);
            yield $index;
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate): iterable
    {
        $queryString = "SELECT
            l.id
            FROM link l
            INNER JOIN folder fo ON l.category = fo.id
            WHERE COALESCE(l.deleted_at, fo.deleted_at) IS NOT NULL
        ";
        $params      = [];
        if ($fromDate) {
            $queryString        .= ' AND COALESCE(l.deleted_at, fo.deleted_at) >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString      .= ' AND COALESCE(l.deleted_at, fo.deleted_at) <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        $query = $this->connector->prepareQuery($queryString);
        $query->execute($params);
        $this->logger->info(sprintf('Deleting search indices for %d links', $query->rowCount()));
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
