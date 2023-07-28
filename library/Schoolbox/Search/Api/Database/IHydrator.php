<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database;

use Schoolbox\Search\Api\Result;

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
     * @param RawMatch $match
     * @return bool
     */
    public function accepts(RawMatch $match): bool;

    /**
     * Hydrate this raw match into a proper result.
     *
     * TODO accept multiple results, hydrate them all
     *
     * @param RawMatch $match
     * @return Result
     */
    public function hydrate(RawMatch $match): Result;
}
