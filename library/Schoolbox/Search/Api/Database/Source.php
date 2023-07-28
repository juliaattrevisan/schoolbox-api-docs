<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database;

/**
 * A source for search results fetched from a database.
 *
 * Contains:
 * * a matcher, for finding search results
 * * a hydrator, for converting those results into something which will then be
 *   rendered
 */
class Source
{
    public function __construct(
        private IMatcher $matcher,
        private IHydrator $hydrator,
    ) {
    }
}
