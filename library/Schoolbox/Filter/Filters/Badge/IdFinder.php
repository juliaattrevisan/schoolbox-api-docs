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


    protected function buildQuery(array $completeFilters,bool $deleted) : array
    {
        // TODO: Implement buildQuery() method.
    }
}
