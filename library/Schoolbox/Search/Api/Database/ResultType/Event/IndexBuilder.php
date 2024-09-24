<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Event;

use Alaress_Date_Format;
use Schoolbox\Calendar\Event;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;

/**
 * Index builder for events.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * This builder works with calendar event objects.
     *
     * @param object $subject
     * @return bool
     */
    protected function accepts(object $subject): bool
    {
        return $subject instanceof Event;
    }

    /**
     * @inheritdoc
     */
    protected function augmentQueryParams(object $subject, string $baseQuery, array $baseParams): array
    {
        return [
            $baseQuery . ' AND e.id = :id',
            $baseParams + ['id' => $subject->getId()],
        ];
    }

    /**
     * @inheritdoc
     */
    public function buildUpdates(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
    {
        $queryString = "
            SELECT
                e.id,
                e.title,
                e.start_datetime AS date,
                e.author_id AS created_by,
                e.detail AS fulltext_index1,
                e.location AS fulltext_index2
            FROM event e
            WHERE e.deleted_at IS NULL
        ";
        $params = [];
        if ($fromDate) {
            $queryString .= ' AND e.updated_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND e.updated_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        if ($subject) {
            if (!$this->accepts($subject)) {
                return;
            }
            [$queryString, $params] = $this->augmentQueryParams($subject, $queryString, $params);
        }
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding events...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d events', $query->rowCount()));
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
            $index = $this->addFulltextIndex($index, $row['fulltext_index1'], self::INDEX_STRATEGY_HTML);
            $index = $this->addFulltextIndex($index, $row['fulltext_index2'], self::INDEX_STRATEGY_HTML);
            yield $index;
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
    {
        $queryString = "SELECT
            e.id
            FROM event e
            WHERE e.deleted_at IS NOT NULL
        ";
        $params       = [];
        if ($fromDate) {
            $queryString        .= ' AND e.deleted_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString      .= ' AND e.deleted_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        if ($subject) {
            if (!$this->accepts($subject)) {
                return;
            }
            [$queryString, $params] = $this->augmentQueryParams($subject, $queryString, $params);
        }
        $query = $this->connector->prepareQuery($queryString);
        $query->execute($params);
        $this->logger->info(sprintf('Deleting search indices for %d events', $query->rowCount()));
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
