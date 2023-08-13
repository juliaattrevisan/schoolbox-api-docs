<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database;

use Schoolbox\Search\Api\Cursor\DataObject as CursorDataObject;
use Schoolbox\Search\Api\SearchFilter\DataObject as FilterDataObject;

/**
 * A builder for a search within a database.
 */
interface IQueryParamBuilder
{
    /**
     * Builds a query and parameters for finding matches.
     *
     * @param string                $keyword The search term.
     * @param FilterDataObject      $filter  A set of filters to apply at the query building level.
     * @param CursorDataObject|null $cursor  A cursor to apply when building the query.
     * @param int                   $limit   The maximum number of results to retrieve.
     * @return QueryParams
     */
    public function buildMatchQueryParams(
        string $keyword,
        FilterDataObject $filter,
        ?CursorDataObject $cursor,
        int $limit,
    ): QueryParams;
}
