<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Blog;

use Alaress_Date_Format;
use Schoolbox\Blog\Homepage\Post\Model;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;

/**
 * Index builder for blogs.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * This builder works with blog objects.
     *
     * @param object $item
     * @return int|string|null
     */
    public function extractItemId(object $item): int|string|null
    {
        if ($item instanceof Model) {
            return $item->getId();
        }
        return null;
    }

    /**
     * @inheritdoc
     */
    public function buildUpdates(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, int|string|null $itemId): iterable
    {
        $queryString = "
            SELECT
                b.id,
                b.heading AS title,
                b.updated_at AS date,
                b.owner_id AS created_by,
                f.id AS folder_id,
                b.body AS fulltext_index
            FROM blog b
            JOIN folder f ON b.folder_id = f.id
            WHERE b.deleted_at IS NULL
            AND f.deleted_at IS NULL
        ";
        $params = [];
        if ($fromDate) {
            $queryString .= ' AND b.updated_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND b.updated_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding blog posts...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d blog posts', $query->rowCount()));
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
            $index = $this->addFulltextIndex($index, $row['fulltext_index'], self::INDEX_STRATEGY_HTML);
            yield $index;
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, int|string|null $itemId): iterable
    {
        $queryString = "SELECT
            b.id
            FROM blog b
            JOIN folder f ON b.folder_id = f.id
            WHERE COALESCE(b.deleted_at, f.deleted_at) IS NOT NULL
        ";
        $params      = [];
        if ($fromDate) {
            $queryString        .= ' AND COALESCE(b.deleted_at, f.deleted_at) >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString      .= ' AND COALESCE(b.deleted_at, f.deleted_at) <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        $query = $this->connector->prepareQuery($queryString);
        $query->execute($params);
        $this->logger->info(sprintf('Deleting search indices for %d blog posts', $query->rowCount()));
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
