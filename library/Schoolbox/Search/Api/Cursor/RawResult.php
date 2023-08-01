<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Cursor;

use Alaress_Date_Format;

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
     * @param int                      $id        The ID of the search result subject.
     * @param string                   $title     The title of the search result.
     * @param string                   $type      The type of search result.
     * @param Alaress_Date_Format|null $date      The date (if any) of the search result.
     * @param float                    $relevance A relevancy score for this result: should be between 0 and 1
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $type,
        public ?Alaress_Date_Format $date,
        public float $relevance,
    ) {
    }
}
