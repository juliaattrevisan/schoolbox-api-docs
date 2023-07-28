<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Query;

use Schoolbox\Search\Api\Cursor;
use Schoolbox\Search\Api\Filter;

/**
 * A builder for a search result built from a database query.
 */
interface ISearchQuery
{
    /**
     * Build a search query and its parameters.
     *
     * @param string $keyword
     * @param Filter $filter
     * @param Cursor $cursor
     * @param int    $limit
     * @return array{query: string, params: array} A query and params.
     *                                             Intended to be used in a database query, and joined together with
     *                                             other `buildQuery` results into a `UNION` query.
     */
    public function buildQuery(string $keyword, Filter $filter, Cursor $cursor, int $limit): array;
}
