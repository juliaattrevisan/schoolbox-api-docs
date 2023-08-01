<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database;

use Schoolbox\Search\Api\Cursor\DataObject as Cursor;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Filter\DataObject as Filter;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\Search\Api\Hydrator\IHydrator;

/**
 * A source for search results fetched from a database.
 *
 * Contains and is a proxy for:
 * * a matcher, for finding search results
 * * a hydrator, for converting those results into something which will then be
 *   rendered
 */
class Source implements IQueryParamBuilder, IHydrator
{
    public function __construct(
        private IQueryParamBuilder $matcher,
        private IHydrator $hydrator,
    ) {
    }

    /**
     * Proxy for `$this->matcher->buildMatchQueryParams`.
     */
    public function buildMatchQueryParams(string $keyword, Filter $filter, ?Cursor $cursor, int $limit): QueryParams
    {
        return $this->matcher->buildMatchQueryParams($keyword, $filter, $cursor, $limit);
    }

    /**
     * Proxy for `$this->hydrator->accepts`.
     */
    public function accepts(RawResult $match): bool
    {
        return $this->hydrator->accepts($match);
    }

    /**
     * Proxy for `$this->hydrator->hydrate`.
     */
    public function hydrate(RawResult $match): FullResult
    {
        return $this->hydrator->hydrate($match);
    }
}
