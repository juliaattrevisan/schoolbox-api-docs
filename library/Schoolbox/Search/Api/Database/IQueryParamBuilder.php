<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database;

use Schoolbox\Search\Api\Cursor\DataObject as CursorDataObject;
use Schoolbox\Search\Api\SearchFilter\DataObject as FilterDataObject;
use Schoolbox\User;

/**
 * A builder for a search within a database.
 */
interface IQueryParamBuilder
{
    /**
     * Builds a query and parameters for finding matches.
     *
     * @param string                $keyword      The search term.
     * @param FilterDataObject      $filter       A set of filters to apply at the query building level.
     * @param CursorDataObject|null $cursor       A cursor to apply when building the query.
     * @param int                   $limit        The maximum number of results to retrieve.
     *
     * @return QueryParams|null The query and parameters to use to find matches,
     *                          or null if this builder is not to be used at
     *                          all (e.g. if `$filter` excludes all possible
     *                          matches).
     */
    public function buildMatchQueryParams(
        string $keyword,
        FilterDataObject $filter,
        ?CursorDataObject $cursor,
        int $limit,
        string $paramKeySuffix = ''
    ): ?QueryParams;

    /**
     * @param User $user
     * @return bool
     * @see IHydrator/isRelevantToUser() (Similar function for Hydrators)
     */
    public function isRelevantToUser(User $user): bool;
}
