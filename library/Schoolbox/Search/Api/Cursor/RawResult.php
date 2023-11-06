<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Cursor;

/**
 * Data object for a single raw search result from a cursor. (This is classed as
 * a raw result because it contains no information beyond what is necessary to
 * find the full result.)
 *
 * This will later be fleshed out into a full result by a `IHydrator` instance.
 */
class RawResult
{
    /**
     * Constructor
     *
     * @param string $id        The ID of the search result subject (string not int because we need to accommodate compound IDs)
     * @param string $title     The title of the search result.
     * @param string $type      The type of search result.
     * @param float  $relevance A relevancy score for this result: should be between 0 and 1
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $type,
        public float $relevance,
    ) {
    }
}
