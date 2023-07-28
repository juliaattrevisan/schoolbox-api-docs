<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database;

/**
 * Data object for a query + params set, used to populate a `RawMatch` object.
 *
 * This object's `$query` is intended to be joined with other `QueryParams`
 * objects into a single `UNION` query.
 */
class QueryParams
{
    /**
     * Constructor
     *
     * @param string $query  The database query to run.
     * @param array  $params The parameters to pass to the database query.
     */
    public function __construct(
        public string $query,
        public array $params,
    ) {
    }
}
