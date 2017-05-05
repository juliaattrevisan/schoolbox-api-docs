<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment;

use Schoolbox\Collection;

/**
 * Enables unit testing of the parent's logic by exposing internal methods.
 */
class TestableIdFinder extends IdFinder
{

    /**
     * @param array $completeFilters
     * @return bool
     */
    public function getFiltersGuaranteeEmptySet(array $completeFilters)
    {
        return $this->doFiltersGuaranteeEmptySet($completeFilters);
    }

    /**
     * @param array $completeFilters
     * @return bool
     */
    public function getDoContextFiltersGuaranteeEmptySet(array $completeFilters)
    {
        return $this->doContextFiltersGuaranteeEmptySet($completeFilters);
    }

    /**
     * @param array $completeFilters
     * @return bool
     */
    public function getDoNonContextFiltersGuaranteeEmptySet(array $completeFilters)
    {
        return $this->doNonContextFiltersGuaranteeEmptySet($completeFilters);
    }

    /**
     * @param int[][]|null[] $completeFilters All keys must exists (to avoid needing a heap of isset() calls)
     * @return mixed[] UNION sub-query string + params array
     */
    public function getSqlStringForFilters(array $completeFilters)
    {
        return $this->buildQuery($completeFilters, false);
    }


    /**
     * Inserts null values for missing filters and ensures any scalars are wrapped in Collections
     *
     * @param $filters
     * @return Collection[]|null[]|mixed[][]
     */
    public function insertMissingFilters($filters)
    {
        return $this->normaliseFilters($filters);
    }
}
