<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Search\Api\Cursor\DataObject as CursorDataObject;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\SearchFilter\DataObject as FilterDataObject;
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
            if ($queryParams) {
                $queries[] = $queryParams->query;
                $params    = array_merge($params, $queryParams->params);
            }
        }
        if (empty($queries)) {
            return [];
        }

        // UNION all queries together
        $queryString = 'SELECT * FROM (('
            . implode(') UNION ALL (', $queries)
            . ')) AS combined';

        // Apply ordering here too
        $queryString .= '
            ORDER BY
                relevance DESC,
                title ASC,
                type ASC,
                id ASC
            LIMIT :limit
        ';
        $params['limit'] = $limit;

        $rawMatches = [];
        foreach ($this->connector->executeQuery($queryString, $params) as $row) {
            $rawMatches[] = new RawResult(
                (int)$row['id'],
                $row['title'],
                $row['type'],
                (float)$row['relevance'],
                (int)$row['folderId']
            );
        }

        return $rawMatches;
    }
}
