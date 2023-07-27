<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Query;

use Schoolbox\Search\Api\Filter;

/**
 * Search for a user which matches a particular keyword and filter.
 */
class User implements ISearchQuery
{
    /**
     * @inheritdoc
     */
    public function buildQuery(string $keyword, Filter $filter, string $cursor, int $limit): array
    {

    }
}
