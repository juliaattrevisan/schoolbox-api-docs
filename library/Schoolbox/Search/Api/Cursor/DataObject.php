<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Cursor;

/**
 * Data object for a cursor.
 *
 * The cursor is a pointer to the last result returned by the search: it is used
 * to fetch the next page of results.
 */
class DataObject
{
    /**
     * Constructor
     *
     * @param float  $relevance The relevance of the last result: should be a value between 0 and 1.
     * @param string $title     The title of the last result.
     * @param string $type      The type of the last result.
     * @param string $id        The ID of the last result (string not int because we need to accommodate compound IDs).
     */
    public function __construct(
        public float $relevance,
        public string $title,
        public string $type,
        public string $id,
    ) {
    }
}
