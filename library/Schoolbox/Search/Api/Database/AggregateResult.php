<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database;

use Alaress\Database\Connector\IConnector;
use Alaress_Date_Format;
use Schoolbox\Search\Api\Cursor\DataObject;
use Schoolbox\Search\Api\Filter;
use Schoolbox\Search\Api\ISource;
use Schoolbox\Search\Api\Result;

class AggregateResult implements ISource
{
    /**
     * @var Source[]
     */
    private array $sources;

    public function __construct(
        private IConnector $connector,
        Source ...$queries,
    ) {
        $this->sources = $queries;
    }

    /**
     * @param string          $keyword
     * @param Filter          $filter
     * @param DataObject|null $cursor
     * @param int             $limit
     * @return array<Result>
     */
    public function search(string $keyword, Filter $filter, ?DataObject $cursor, int $limit): array
    {
        if (empty($this->sources)) {
            return [];
        }
        $queries = [];
        $params  = [];
        foreach ($this->sources as $source) {
            $queryParams = $source->buildMatchQueryParams($keyword, $filter, $cursor, $limit);
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

        $results = [];
        foreach ($rawMatches as $rawMatch) {
            // TODO assume each source's raw matches can also accept results
            foreach ($this->sources as $source) {
                if ($source->accepts($rawMatch)) {
                    $results[] = $source->hydrate($rawMatch);
                    break;
                }
            }
        }

        return $results;
    }
}
