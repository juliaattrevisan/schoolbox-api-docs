<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api;

use Schoolbox\Search\Api\Cursor\DataObject;

/**
 * A source for a search.
 */
interface ISource
{
    /**
     * Search this source.
     *
     * @param string          $keyword A keyword to search for.
     * @param Filter          $filter  A filter for search results.
     * @param DataObject|null $cursor  A pointer to the last result in the
     *                                 previous search.
     * @param int             $limit   A limit of results to fetch.
     * @return array<Result>
     */
    public function search(string $keyword, Filter $filter, ?DataObject $cursor, int $limit): array;
}
