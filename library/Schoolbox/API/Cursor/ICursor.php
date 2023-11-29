<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Cursor;

use Schoolbox\Collection;
use Schoolbox\User;

/**
 * Interface for all cursor implementations.
 *
 * Cursoring is a standard pagination method for APIs:
 * * the initial request to an endpoint will retrieve one page of results, and a
 *   pointer to the next page
 * * to retrieve the next page of results: the same endpoint will be queried,
 *   with the cursor just retrieved; this will return the next page, and a
 *   pointer to the one after
 * * when no more pages of results are left to retrieve, a null cursor will be
 *   returned
 *
 * @see https://uxdesign.cc/why-facebook-says-cursor-pagination-is-the-greatest-d6b98d86b6c0
 */
interface ICursor
{
    /**
     * Retrieve an encoded cursor to the next set of results, suitable for
     * injection into a query string.
     *
     * @param Collection $collection A collection of results, likely retrieved
     *                               by `retrieveResults`.
     * @return string|null The cursor for the next set of results, or null if
     *                     there are no more results to fetch.
     */
    public function next(Collection $collection): ?string;


    /**
     * Retrieve an encoded cursor to the current set of results, suitable for
     * injection into a query string.
     *
     * This may be useful when retrieving a result set before a cursor is known.
     *
     * @param Collection $collection A collection of results, likely retrieved
     *                               by `retrieveResults`.
     * @return string|null The cursor for the current set of results, or null if
     *                     there are no results at all to fetch.
     */
    public function current(Collection $collection): ?string;


    /**
     * Retrieve the total number of results that could possibly be retrieved by
     * the result set iterated by this cursor, or `null` if this number is
     * unknown.
     *
     * @return int|null
     */
    public function retrieveResultCount(): ?int;


    /**
     * Retrieve the page of results that `$encodedCursor` points to.
     *
     * If `$encodedCursor` is null, the first page of results should be
     * retrieved.
     *
     * @param string|null $encodedCursor The current encoded cursor, or null if
     *                                   no cursor is yet known.
     * @return Collection
     */
    public function retrieveResults(?string $encodedCursor, User $loggedInUser): Collection;
}
