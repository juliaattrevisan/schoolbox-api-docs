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
     * Hydrate raw matches into a proper result.
     *
     * This need not return a result for all `$matches`, but key associations
     * should be preserved so that the raw result may be associated with a full
     * result.
     *
     * @param array<int, RawResult> $matches A set of matches to hydrate.
     * @return array<int, FullResult>
     */
    public function hydrate(array $matches): array;
}
