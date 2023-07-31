<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database;

use Alaress\Database\Connector\IConnector;
use Alaress_Date_Format;
use Schoolbox\Search\Api\Cursor\DataObject as Cursor;
use Schoolbox\Search\Api\Filter\DataObject as Filter;
use Schoolbox\Search\Api\ISource;

class AggregateResult implements ISource
{
    /**
     * @var IMatcher[]
     */
    private array $matchers;

    public function __construct(
        private IConnector $connector,
        IMatcher ...$matchers,
    ) {
        $this->matchers = $matchers;
    }

    /**
     * @param string      $keyword
     * @param Filter      $filter
     * @param Cursor|null $cursor
     * @param int         $limit
     * @return array<RawMatch>
     */
    public function search(string $keyword, Filter $filter, ?Cursor $cursor, int $limit): array
    {
        if (empty($this->matchers)) {
            return [];
        }
        $queries = [];
        $params  = [];
        foreach ($this->matchers as $matcher) {
            $queryParams = $matcher->buildMatchQueryParams($keyword, $filter, $cursor, $limit);
            $queries[]   = $queryParams->query;
            $params      = array_merge($params, $queryParams->params);
        }

        $query  = '(' . implode(') UNION (', $queries) . ')';

        // Apply ordering here too
        $query .= '
            ORDER BY
                relevance DESC,
                title ASC,
                type ASC,
                id ASC
            LIMIT :limit
        ';
        $params['limit'] = $limit;

        $rawMatches = [];
        foreach ($this->connector->executeQuery($query, $params) as $row) {
            $rawMatches[] = new RawMatch(
                (int)$row['id'],
                $row['title'],
                $row['type'],
                Alaress_Date_Format::newDatabaseDate($row['date']),
                $row['relevance'],
            );
        }

        return $rawMatches;
    }
}
