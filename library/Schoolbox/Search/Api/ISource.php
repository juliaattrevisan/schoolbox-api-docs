<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api;

use Schoolbox\Search\Api\Cursor\DataObject as Cursor;
use Schoolbox\Search\Api\Filter\DataObject as Filter;
use Schoolbox\Search\Api\Result\DataObject as Result;

/**
 * A source for a search.
 */
interface ISource
{
    /**
     * Search this source.
     *
     * @param string      $keyword A keyword to search for.
     * @param Filter      $filter  A filter for search results.
     * @param Cursor|null $cursor  A pointer to the last result in the previous
     *                             search.
     * @param int         $limit   A limit of results to fetch.
     * @return array<Result>
     */
    public function search(string $keyword, Filter $filter, ?Cursor $cursor, int $limit): array;
}
