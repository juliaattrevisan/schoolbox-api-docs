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
     * @param string                $keyword
     * @param FilterDataObject      $filter
     * @param CursorDataObject|null $cursor
     * @param int                   $limit
     * @return QueryParams
     */
    public function buildMatchQueryParams(
        string $keyword,
        FilterDataObject $filter,
        ?CursorDataObject $cursor,
        int $limit,
    ): QueryParams;
}
