<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Badge;

use Alaress_Date_Format;
use Schoolbox\Learning\Badge\Model;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;

/**
 * Index builder for badges.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * This builder works with badge objects.
     *
     * @param object $subject
     * @return int|string|null
     */
    public function extractItemId(object $subject): int|string|null
    {
        if ($subject instanceof Model) {
            return $subject->getId();
        }
        return null;
    }

    /**
     * @inheritdoc
     */
    public function buildUpdates(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
    {
        $queryString = "
            SELECT
                b.id,
                b.name AS title,
                b.updated_at AS date,
                b.created_by,
                b.body AS fulltext_index
            FROM badge b
            WHERE b.deleted_at IS NULL
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
        if ($subject) {
            $itemId = $this->extractItemId($subject);
            if (!isset($itemId)) {
                return;
            }
            $queryString .= ' AND b.id = :id';
            $params['id'] = $itemId;
        }
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding badges...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d badges', $query->rowCount()));
        foreach ($query as $row) {
            $index = $this->buildIndex(
                (string)$row['id'],
                $row['title'],
                Hydrator::BASE_TYPE,
                $row['date'],
                true,
                $row['created_by'],
                null,
            );
            $index = $this->addFulltextIndex($index, $row['fulltext_index'], self::INDEX_STRATEGY_HTML);
            yield $index;
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
    {
        $queryString = "SELECT id FROM badge WHERE deleted_at IS NOT NULL";
        $params      = [];
        if ($fromDate) {
            $queryString        .= ' AND deleted_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString      .= ' AND deleted_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        if ($subject) {
            $itemId = $this->extractItemId($subject);
            if (!isset($itemId)) {
                return;
            }
            $queryString .= ' AND b.id = :id';
            $params['id'] = $itemId;
        }
        $query = $this->connector->prepareQuery($queryString);
        $query->execute($params);
        $this->logger->info(sprintf('Deleting search indices for %d badges', $query->rowCount()));
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
