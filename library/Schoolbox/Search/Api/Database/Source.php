<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database;

use Schoolbox\Search\Api\Cursor\DataObject;
use Schoolbox\Search\Api\Filter;
use Schoolbox\Search\Api\Result;

/**
 * A source for search results fetched from a database.
 *
 * Contains and is a proxy for:
 * * a matcher, for finding search results
 * * a hydrator, for converting those results into something which will then be
 *   rendered
 */
class Source implements IMatcher, IHydrator
{
    public function __construct(
        private IMatcher $matcher,
        private IHydrator $hydrator,
    ) {
    }

    /**
     * Proxy for `$this->matcher->buildMatchQueryParams`.
     */
    public function buildMatchQueryParams(string $keyword, Filter $filter, ?DataObject $cursor, int $limit): QueryParams
    {
        return $this->matcher->buildMatchQueryParams($keyword, $filter, $cursor, $limit);
    }

    /**
     * Proxy for `$this->hydrator->accepts`.
     */
    public function accepts(RawMatch $match): bool
    {
        return $this->hydrator->accepts($match);
    }

    /**
     * Proxy for `$this->hydrator->hydrate`.
     */
    public function hydrate(RawMatch $match): Result
    {
        return $this->hydrator->hydrate($match);
    }
}
