<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Search\Api\Cursor;
use Schoolbox\Search\Api\Filter;
use Schoolbox\Search\Api\ISource;
use Schoolbox\Search\Api\Query\ISearchQuery;

class AggregateResult implements ISource
{
    /**
     * @var ISearchQuery[]
     */
    private array $queries;

    public function __construct(
        private IConnector $connector,
        ISearchQuery ...$queries,
    ) {
        $this->queries = $queries;
    }

    /**
     * @param string      $keyword
     * @param Filter      $filter
     * @param Cursor|null $cursor
     * @param int         $limit
     * @return array
     */
    public function search(string $keyword, Filter $filter, ?Cursor $cursor, int $limit): array
    {
        if (empty($this->queries)) {
            return [];
        }
        $queries = [];
        $params  = [];
        foreach ($this->queries as $query) {
            $result = $query->buildQuery($keyword, $filter, $cursor, $limit);
            $queries[] = $result['query'];
            $params = array_merge($params, $result['params']);
        }

        $query  = '(' . implode(') UNION (', $queries) . ')';

        // Apply ordering here too
        $query .= '
            ORDER BY
                relevance DESC,
                title ASC,
                type ASC,
                id ASC
        ';

        $result = $this->connector->executeQuery($query, $params);
        return $result->fetchAll();
    }
}
