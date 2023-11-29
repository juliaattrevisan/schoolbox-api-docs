<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database;

use Alaress\Database\Connector\IConnector;
use InvalidArgumentException;
use Schoolbox\Search\Api\Cursor\DataObject as CursorDataObject;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\SearchFilter\DataObject as FilterDataObject;
use Schoolbox\Search\Api\Source\ISource;
use Schoolbox\User;

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
     * @param User                  $loggedInUser
     * @param string                $keyword
     * @param FilterDataObject      $filter
     * @param CursorDataObject|null $cursor
     * @param int                   $limit
     * @return array<RawResult>
     */
    public function search(User $loggedInUser, string $keyword, FilterDataObject $filter, ?CursorDataObject $cursor, int $limit): array
    {
        [$queryString, $params] = $this->getQueryStringAndParams(
            $loggedInUser,
            $keyword,
            $filter,
            $cursor,
            $limit,
            'select'
        );

        if ($queryString === '') {
            // This can happen if a filter has been specified that guarantees the query will return no results.
            // E.g. Specifying a folder and restricting to users.  Users are not connected to folders, so no result possible.
            return [];
        }

        $rawMatches = [];
        foreach ($this->connector->executeQuery($queryString, $params) as $row) {
            $rawMatches[] = new RawResult(
                (string)$row['id'],
                $row['title'],
                $row['type'],
                (float)$row['relevance'],
            );
        }

        return $rawMatches;
    }

    /**
     * @param User                  $loggedInUser
     * @param string                $keyword
     * @param FilterDataObject      $filter
     * @param CursorDataObject|null $cursor
     * @param string                $paramKeySuffix - Suffix to add to parameter keys
     *                                                (useful if building repeated subqueries in larger query).
     * @return array
     */
    public function getMoreResultsExistQueryAndParams(
        User $loggedInUser,
        string $keyword,
        FilterDataObject $filter,
        ?CursorDataObject $cursor,
        string $paramKeySuffix = ''
    ): array {
        return $this->getQueryStringAndParams(
            $loggedInUser,
            $keyword,
            $filter,
            $cursor,
            null,
            'exists',
            $paramKeySuffix
        );
    }

    /**
     * @param User                  $loggedInUser
     * @param string                $keyword
     * @param FilterDataObject      $filter
     * @param CursorDataObject|null $cursor
     * @param int|null              $limit
     * @param string                $queryType      - Expected values: {'select', 'exists'}.
     * @param string                $paramKeySuffix - Suffix to add to parameter keys
     *                                                (useful if building repeated subqueries in larger query).
     * @return array
     */
    private function getQueryStringAndParams(
        User $loggedInUser,
        string $keyword,
        FilterDataObject $filter,
        ?CursorDataObject $cursor,
        ?int $limit,
        string $queryType,
        string $paramKeySuffix = ''
    ): array {
        if (empty($this->queryParamBuilders)) {
            return ['', []];
        }

        $queries = [];
        $params  = [];
        $limit   ??= 1;
        foreach ($this->queryParamBuilders as $queryParamBuilder) {
            if (!$queryParamBuilder->isRelevantToUser($loggedInUser)) {
                continue;
            }

            $queryParams = $queryParamBuilder->buildMatchQueryParams($keyword, $filter, $cursor, $limit, $paramKeySuffix);
            if ($queryParams) {
                $queries[] = $queryParams->query;
                $params    = array_merge($params, $queryParams->params);
            }
        }

        if (empty($queries)) {
            return ['', []];
        }

        $existsOnly = match ($queryType) {
            'select' => false,
            'exists' => true,
            default => throw new InvalidArgumentException("Unexpected queryType '$queryType'."),
        };

        // UNION all queries together.
        $queryString = (
            ($existsOnly ? 'IF(EXISTS(' : 'SELECT * FROM (') .
            '(' . implode(') UNION ALL (', $queries) . ')' .
            ($existsOnly ? '), 1, 0)' : ') AS combined')
        );

        if (!$existsOnly) {
            // Apply ordering here too.
            // IMPORTANT:
            //  * Order here must match order in library/Schoolbox/Search/Api/Database/BaseQueryParamBuilder->buildMatchQueryParams().
            //  * The order of `id` is DESC because all other things being equal, we want more recently created rows to
            //    appear before less recently created rows.
            $queryString .= "
                ORDER BY
                    relevance DESC,
                    title ASC,
                    type ASC,
                    id DESC
                LIMIT :overallLimit$paramKeySuffix
            ";
            $params["overallLimit$paramKeySuffix"] = $limit;
        }

        return [$queryString, $params];
    }
}
