<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge;

use Schoolbox\Filter\AbstractIdFinder;

/**
 * ID finder for the badge filter.
 */
class IdFinder extends AbstractIdFinder
{
    /**
     * Return false for now
     *
     * @param array $completeFilters
     * @return bool
     */
    protected function doFiltersGuaranteeEmptySet(array $completeFilters): bool
    {
        return false;
    }


    /**
     * Don't simplify filters for now
     *
     * @param array $completeFilters
     * @return array
     */
    protected function simplifyFilters(array $completeFilters): array
    {
        return $completeFilters;
    }


    /**
     * Retrieve ID of all badges which match the filters.
     *
     * @param array $completeFilters
     * @param bool  $deleted
     * @return array
     */
    protected function buildQuery(array $completeFilters, bool $deleted): array
    {
        $categoryIds = $completeFilters[Category\Definition::class] ?? null;
        $pb          = $this->getMapper()->getPredicateBuilder();
        $hasWhere    = false;
        if (isset($categoryIds) && count($categoryIds) > 0) {
            $hasWhere = true;
            $pb = $pb->in('category_id', $categoryIds);
        }
        if (!$deleted) {
            $hasWhere = true;
            $pb->isNull('deleted_at');
        }

        $query = 'SELECT id FROM badge';
        if ($hasWhere) {
            $query .= ' WHERE ' . $pb->getWhereIntersect();
        }
        $params = $pb->getParams();
        return [$query, $params];
    }
}
