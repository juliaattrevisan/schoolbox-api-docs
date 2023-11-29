<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Source;

use Schoolbox\Search\Api\Cursor\DataObject as CursorDataObject;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\SearchFilter\DataObject as FilterDataObject;
use Schoolbox\User;

/**
 * A source for a search.
 */
interface ISource
{
    /**
     * Search this source.
     *
     * @param User                  $loggedInUser The logged-in user.
     * @param string                $keyword      A keyword to search for.
     * @param FilterDataObject      $filter       A filter for search results.
     * @param CursorDataObject|null $cursor       A pointer to the last result in the previous search.
     * @param int                   $limit        A limit of results to fetch.
     * @return array<RawResult>
     */
    public function search(User $loggedInUser, string $keyword, FilterDataObject $filter, ?CursorDataObject $cursor, int $limit): array;

    /**
     * Return a query for checking whether more results exist.
     *
     * @param User                  $loggedInUser     The logged-in user.
     * @param string                $keyword          A keyword to search for.
     * @param FilterDataObject      $filter           A filter for search results.
     * @param CursorDataObject|null $cursor           A pointer to the last result in the previous search.
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
    ): array;
}
