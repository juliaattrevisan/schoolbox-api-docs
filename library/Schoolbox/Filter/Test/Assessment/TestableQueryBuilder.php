<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment;

/**
 * Enables unit testing of the QueryBuilder's logic by exposing internal methods.
 */
class TestableQueryBuilder extends QueryBuilder
{

    /**
     * @param array $filters
     * @return bool
     */
    public function getAreResultsPossible(array $filters)
    {
        return $this->areResultsPossible($filters);
    }

    /**
     * @param int[][]|null[] $completeFilters All keys must exists (to avoid needing a heap of isset() calls)
     * @return mixed[] UNION sub-query string + params array
     */
    public function getSqlStringForFilters(array $completeFilters)
    {
        return $this->buildQuery($completeFilters);
    }
}
