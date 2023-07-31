<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database;

use Schoolbox\Search\Api\Cursor\DataObject as Cursor;
use Schoolbox\Search\Api\Filter\DataObject as Filter;

/**
 * A builder for a search within a database.
 */
interface IQueryParamBuilder
{
    /**
     * Builds a query and parameters for finding matches.
     *
     * @param string      $keyword
     * @param Filter      $filter
     * @param Cursor|null $cursor
     * @param int         $limit
     * @return QueryParams
     */
    public function buildMatchQueryParams(string $keyword, Filter $filter, ?Cursor $cursor, int $limit): QueryParams;
}
