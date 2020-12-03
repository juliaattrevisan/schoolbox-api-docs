<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);


namespace Schoolbox\Filter\Filters\Badge;


use Schoolbox\Collection;use Schoolbox\Filter\AbstractIdFinder;use Schoolbox\Filter\BrokenFilterException;


class IdFinder extends AbstractIdFinder
{
    /**
     * Return false for now
     *
     * @param array $completeFilters
     * @return bool
     */
    protected function doFiltersGuaranteeEmptySet(array $completeFilters) : bool
    {
        return false;
    }


    /**
     * Don't simplify filters for now
     *
     * @param array $completeFilters
     * @return array
     */
    protected function simplifyFilters(array $completeFilters) : array
    {
        return $completeFilters;
    }


    /**
     * Retrieve ID of all badges which match the filters.
     *
     * @param array $completeFilters
     * @param bool $deleted
     * @return array
     */
    protected function buildQuery(array $completeFilters, bool $deleted) : array
    {
        $categoryIds = $completeFilters[Category\Definition::class];
        $pb          = $this->getMapper()->getPredicateBuilder()
                                         ->in('category_id', $categoryIds);

        $query  = 'SELECT b.id FROM badge b WHERE ' . $pb->getWhereIntersect();
        $params = $pb->getParams();
        return [$query, $params];
    }
}
