<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Page;

use Alaress_Date_Format;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;

/**
 * Index builder for pages/folders.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * @inheritdoc
     */
    public function buildUpdates(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate): iterable
    {
        $queryString = "
            SELECT
                f.id,
                f.name,
                f.updated_at AS date,
                f.created_by,
                f.id AS folder_id
            FROM folder f
            WHERE f.deleted_at IS NULL
        ";
        $params = [];
        if ($fromDate) {
            $queryString .= ' AND f.updated_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND f.updated_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding pages/folders...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d pages/folders', $query->rowCount()));
        foreach ($query as $row) {
            $index = $this->buildIndex(
                (string)$row['id'],
                $row['name'],
                Hydrator::BASE_TYPE,
                $row['date'],
                true,
                $row['created_by'],
                $row['folder_id'],
            );
            $index = $this->addFulltextIndex($index, $row['name'], self::INDEX_STRATEGY_TEXT);
            yield $index;
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate): iterable
    {
        $queryString = "SELECT
            f.id
            FROM folder f
            WHERE f.deleted_at IS NOT NULL
        ";
        $params = [];
        if ($fromDate) {
            $queryString .= ' AND f.deleted_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND f.deleted_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        $query = $this->connector->prepareQuery($queryString);
        $query->execute($params);
        $this->logger->info(sprintf('Deleting search indices for %d pages/folders', $query->rowCount()));
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
