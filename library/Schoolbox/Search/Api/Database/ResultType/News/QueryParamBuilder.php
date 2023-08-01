<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\News;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Quoter\IQuoter;
use Schoolbox\IConfigLoader;
use Schoolbox\Search\Api\Cursor\DataObject as CursorDataObject;
use Schoolbox\Search\Api\Database\IQueryParamBuilder;
use Schoolbox\Search\Api\Database\QueryParams;
use Schoolbox\Search\Api\Filter\DataObject as FilterDataObject;

/**
 * Query parameter builder for a search for news.
 *
 * This may match an article on:
 * - title
 * - blurb
 * - body
 */
class QueryParamBuilder implements IQueryParamBuilder
{
    /**
     * Constructor
     *
     * @param IConfigLoader $config
     * @param IQuoter       $quoter
     */
    public function __construct(
        private IConfigLoader $config,
        private IQuoter $quoter
    ) {
    }

    /**
     * @inheritdoc
     */
    public function buildMatchQueryParams(string $keyword, FilterDataObject $filter, ?CursorDataObject $cursor, int $limit): QueryParams
    {
    }
}
