<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Hydrator;

use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\SearchFilter;
use Schoolbox\User;

/**
 * A hydrator for search result matches.
 *
 * Responsible for:
 * - converting a `RawResult` into a `FullResult`, by populating and fetching
 *   related data for the subject of the result
 * - determining whether the result should even be shown to the viewing user
 */
interface IHydrator
{
    /**
     * Will this hydrator accept this raw match?
     *
     * @param RawResult $match
     * @return bool
     */
    public function accepts(RawResult $match): bool;

    /**
     * Hydrate a set of raw matches into a proper result.
     *
     * This need not return a result for all `$matches`, but key associations
     * should be preserved so that the raw result may be associated with a full
     * result.
     *
     * @param string                  $keyword    The original search keyword:
     *                                            required so that descriptions may
     *                                            be truncated to around where the
     *                                            keyword is found.
     * @param SearchFilter\DataObject $filter     This is supplied for use cases such as:
     *                                             - The hydrate function for forum search results needs to get the
     *                                               number of matching posts that occur in each matching forum thread.
     *                                               This requires another database query, and requires the filter info.
     * @param array<int, RawResult>   $matches    A set of matches to hydrate.
     * @param User                    $viewer     The user viewing the search result.
     * @param String                  $currentUrl Useful for setting 'returnUrl' URL params on linked pages.
     * @return array<int, FullResult|null>
     */
    public function hydrate(
        string $keyword, SearchFilter\DataObject $filter, array $matches, User $viewer, string $currentUrl
    ): array;
}
