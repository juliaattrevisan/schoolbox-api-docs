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
use Schoolbox\Search\Api\Database\Index\BuildType;

/**
 * Index builder for events.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * This builder works with calendar event objects.
     *
     * @param object    $subject
     * @param BuildType $buildType
     * @return bool
     */
    protected function accepts(object $subject, BuildType $buildType): bool
    {
        return $subject instanceof Event;
    }

    /**
     * @param BuildType $buildType
     * @inheritdoc
     */
    protected function augmentQueryParams(object $subject, BuildType $buildType, string $baseQuery, array $baseParams): array
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
            if (!$this->accepts($subject, BuildType::UPDATE)) {
                return;
            }
            [$queryString, $params] = $this->augmentQueryParams($subject, BuildType::UPDATE, $queryString, $params);
        }
        // Set max_statement_time at the query level to ensure no timeouts even if global max_statement_time is set.
        // The query will not work as expected without the CAST, even though a float value is supplied.
        $queryString = "SET STATEMENT max_statement_time = CAST(:maxStatementTime AS DECIMAL(4,4)) FOR\n$queryString";
        $params['maxStatementTime'] = BaseBuilder::QUERY_LEVEL_MAX_STATEMENT_TIME;
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding events...');
        $t0 = microtime(true);
        $query->execute($params);
        $t1 = microtime(true);
        $this->logger->debug('Done.  Time taken: ' . round($t1 - $t0, 4) . ' seconds.');
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
        // Select all search_index rows linked to deleted blogs.
        $queryString = '
            SELECT si.identifier, si.type
            FROM search_index si
            JOIN event e ON si.identifier = e.id
            WHERE si.type = "' . Hydrator::BASE_TYPE . '"
            AND e.deleted_at IS NOT NULL
        ';
        $params = [];
        if ($fromDate) {
            $queryString .= ' AND e.deleted_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND e.deleted_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        if ($subject) {
            if (!$this->accepts($subject, BuildType::DELETE)) {
                return;
            }
            [$queryString, $params] = $this->augmentQueryParams($subject, BuildType::DELETE, $queryString, $params);
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
