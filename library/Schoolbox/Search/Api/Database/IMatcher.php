<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database;

use Schoolbox\Search\Api\Cursor\DataObject;
use Schoolbox\Search\Api\Filter;

/**
 * A builder for a search result built from a database query.
 */
interface IMatcher
{
    /**
     * Builds a query and parameters for finding matches.
     *
     * @param string          $keyword
     * @param Filter          $filter
     * @param DataObject|null $cursor
     * @param int             $limit
     * @return QueryParams
     */
    public function buildMatchQueryParams(string $keyword, Filter $filter, ?DataObject $cursor, int $limit): QueryParams;
}
