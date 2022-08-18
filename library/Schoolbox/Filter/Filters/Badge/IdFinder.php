<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge;

use Schoolbox\Filter\AbstractIdFinder;
use Schoolbox\User;

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
     * @param User  $user
     * @param bool  $deleted
     * @return array
     */
    protected function buildQuery(array $completeFilters, User $user, bool $deleted): array
    {
        $categoryIds = $completeFilters[Category\Definition::class] ?? null;
        $pb          = $this->getMapper()->getPredicateBuilder();

        // Query for categories
        if (isset($categoryIds) && count($categoryIds) > 0) {
            $pb = $pb->in('b.category_id', $categoryIds);
        }

        // Query for curriculum nodes
        $curriculumNodes = $completeFilters[CurriculumNode\Definition::class] ?? null;
        if (isset($curriculumNodes) && count($curriculumNodes) > 0) {
            // Are any codes other than KEY_ALL set?
            // If so, filter on those.
            unset($curriculumNodes[CurriculumNode\Definition::KEY_ALL]);
            if (count($curriculumNodes) > 0) {
                $pb = $pb->any(
                    $this->getMapper()->getPredicateBuilder()
                         ->in('cb.curriculum_node_id', $curriculumNodes)
                );
            }
        }

        // Query for name search
        $nameSearch = $completeFilters[Name\Definition::class] ?? null;
        if (isset($nameSearch) && count($nameSearch) > 0) {
            // Just use the first one, and split it into tokens
            foreach ($nameSearch as $nameSearchString => $_) {
                $names = array_map('trim', preg_split('/\\s+/', (string)$nameSearchString));
                foreach ($names as $name) {
                    if (strlen($name) > 0 && $name !== Name\Definition::KEY_ALL) {
                        $pb = $pb->like(
                            'b.name',
                            '%' . $this->getMapper()->getDatabase()->getQuoter()->escapeForLike($name) . '%'
                        );
                    }
                }
            }
        }

        $allowSetAsGoal = $completeFilters[AllowAsGoal\Definition::class] ?? null;
        if (isset($allowSetAsGoal) && count($allowSetAsGoal) === 1) {
            foreach ($allowSetAsGoal as $isAllowed => $_) {
                $pb = $pb->equals(
                    'b.allow_as_goal',
                    (int)$isAllowed
                );
                break;
            }
        }

        // Filter out deleted badges
        if (!$deleted) {
            $pb->isNull('b.deleted_at');
        }

        $wheres = $pb->getWhere();
        $params = $pb->getParams();

        // Query for year level
        $badgeYearLevels = $completeFilters[YearLevel\Definition::class];
        [$yearLevelJoin, $yearLevelWhere, $yearLevelParams] = $this->getClauseFolderYearLevels($badgeYearLevels);

        // Build query
        $query = "SELECT DISTINCT
            b.id FROM badge b
            LEFT JOIN curriculum_badge cb on b.id = cb.badge_id
        ";

        if ($yearLevelJoin) {
            $query    .= " $yearLevelJoin JOIN badge_year_level fyl on b.id = fyl.badge_id";
            $wheres[] = $yearLevelWhere;
            $params   = array_merge($yearLevelParams, $params);
        }

        if (count($wheres) > 0) {
            $query .= ' WHERE (' . implode(') AND (', $wheres) . ')';
        }
        return [$query, $params];
    }
}
