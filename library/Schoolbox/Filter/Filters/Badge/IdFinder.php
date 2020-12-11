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

        // Query for categories
        if (isset($categoryIds) && count($categoryIds) > 0) {
            $hasWhere = true;
            $pb = $pb->in('b.category_id', $categoryIds);
        }

        // Query for curriculum nodes
        $curriculumNodes = $completeFilters[CurriculumNode\Definition::class] ?? null;
        if (isset($curriculumNodes) && count($curriculumNodes) > 0) {
            $hasWhere = true;
            $subPb    = $this->getMapper()->getPredicateBuilder();
            // is the NONE or ANY option set?
            if (isset($curriculumNodes[CurriculumNode\Definition::KEY_NONE])) {
                unset($curriculumNodes[CurriculumNode\Definition::KEY_NONE]);
                $subPb = $subPb->isNull('cb.badge_id');
            }
            if (isset($curriculumNodes[CurriculumNode\Definition::KEY_ANY])) {
                unset($curriculumNodes[CurriculumNode\Definition::KEY_ANY]);
                $subPb = $subPb->notNull('cb.badge_id');
            }
            if (count($curriculumNodes) > 0) {
                $subPb = $subPb->in('cb.curriculum_node_id', $curriculumNodes);
            }

            $pb = $pb->any($subPb);
        }

        // Filter out deleted badges
        if (!$deleted) {
            $hasWhere = true;
            $pb->isNull('b.deleted_at');
        }

        // Build query
        $query = 'SELECT DISTINCT
            b.id FROM badge b
            LEFT JOIN curriculum_badge cb on b.id = cb.badge_id
        ';
        if ($hasWhere) {
            $query .= ' WHERE ' . $pb->getWhereIntersect();
        }
        $params = $pb->getParams();
        return [$query, $params];
    }
}
