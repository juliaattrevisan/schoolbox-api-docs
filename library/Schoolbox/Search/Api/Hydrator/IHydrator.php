<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Hydrator;

use Schoolbox\Search\Api\Cursor\RawResult;

/**
 * A hydrator for search result matches.
 *
 * Responsible for converting a raw match into an actual result.
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
     * Hydrate this raw match into a proper result.
     *
     * TODO accept multiple matches, hydrate them all at once
     *
     * @param RawResult $match
     * @return FullResult
     */
    public function hydrate(RawResult $match): FullResult;
}
