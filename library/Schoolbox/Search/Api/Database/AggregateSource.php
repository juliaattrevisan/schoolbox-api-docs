<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database;

use Alaress\Database\Connector\IConnector;
use Alaress_Date_Format;
use Schoolbox\Search\Api\Cursor\DataObject as CursorDataObject;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Filter\DataObject as FilterDataObject;
use Schoolbox\Search\Api\Source\ISource;

/**
 * Search results source, aggregating results from multiple database search
 * query builders.
 */
class AggregateSource implements ISource
{
    /**
     * @var IQueryParamBuilder[]
     */
    private array $queryParamBuilders;

    /**
     * Constructor
     *
     * @param IConnector         $connector
     * @param IQueryParamBuilder ...$matchers
     */
    public function __construct(
        private IConnector $connector,
        IQueryParamBuilder ...$matchers,
    ) {
        $this->queryParamBuilders = $matchers;
    }

    /**
     * @param string                $keyword
     * @param FilterDataObject      $filter
     * @param CursorDataObject|null $cursor
     * @param int                   $limit
     * @return array<RawResult>
     */
    public function search(string $keyword, FilterDataObject $filter, ?CursorDataObject $cursor, int $limit): array
    {
        if (empty($this->queryParamBuilders)) {
            return [];
        }
        $queries = [];
        $params  = [];
        foreach ($this->queryParamBuilders as $queryParamBuilder) {
            $queryParams = $queryParamBuilder->buildMatchQueryParams($keyword, $filter, $cursor, $limit);
            $queries[]   = $queryParams->query;
            $params      = array_merge($params, $queryParams->params);
        }

        // TODO consider wrapped SELECT
        // seems to be slower, but is more reliable esp. for multiple UNIONs?
        $query  = '(' . implode(') UNION (', $queries) . ')';
//        $query  = 'SELECT * FROM ((' . implode(') UNION (', $queries) . ')) AS t';

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
            $rawMatches[] = new RawResult(
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
