<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\DifferentiationProfile;

use Schoolbox\Filter\AbstractIdFinder;
use Schoolbox\Filter\Filters\Pastoral\Action;
use Schoolbox\Filter\Filters\Pastoral\Archived;
use Schoolbox\Filter\Filters\Pastoral\Creator;
use Schoolbox\Filter\Filters\Pastoral\Severity;
use Schoolbox\Filter\Filters\Pastoral\Student;
use Schoolbox\Filter\Filters\Pastoral\Type;
use Schoolbox\User;

/**
 * ID finder for the pastoral filter
 */
class IdFinder extends AbstractIdFinder
{
    /**
     * @inheritdoc
     */
    protected function doFiltersGuaranteeEmptySet(array $completeFilters): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    protected function simplifyFilters(array $completeFilters): array
    {
        return $completeFilters;
    }

    /**
     * @inheritdoc
     */
    protected function buildQuery(array $completeFilters, User $user, bool $deleted): array
    {
        $pb = $this->getMapper()->getPredicateBuilder();

        $diffProfileCampuses = $completeFilters[Campus\Definition::class] ?? null;
        if (isset($diffProfileCampuses) && count($diffProfileCampuses) > 0) {
            $pb = $pb->in('sc.campus', $diffProfileCampuses);
        }
        // Query for year level
        $diffProfileYearLevels = $completeFilters[YearLevel\Definition::class];
        if (isset($diffProfileYearLevels) && count($diffProfileYearLevels) > 0) {
            $pb = $pb->in('s.year_id', $diffProfileYearLevels);
        }

        $diffProfileAdjustmentCategories = $completeFilters[AdjustmentCategory\Definition::class] ?? null;
        if (isset($diffProfileAdjustmentCategories) && count($diffProfileAdjustmentCategories) > 0) {
            $pb = $pb->in('ac.id', $diffProfileAdjustmentCategories);
        }

        $diffProfileAdjustmentLevels = $completeFilters[AdjustmentLevel\Definition::class] ?? null;
        if (isset($diffProfileAdjustmentLevels) && count($diffProfileAdjustmentLevels) > 0) {
            $pb = $pb->in('al.id', $diffProfileAdjustmentLevels);
        }

        // Filter out deleted profiles
        if (!$deleted) {
            $pb->isNull('dp.deleted_at');
        }

        $wheres = $pb->getWhere();
        $params = $pb->getParams();

        $query = "SELECT DISTINCT dp.id
                  FROM differentiation_profiles dp
                  RIGHT JOIN pastoral p ON dp.student_id = p.student_id
                  INNER JOIN user s ON p.student_id = s.id
                  INNER JOIN userCampus sc ON s.id = sc.uid
                  LEFT JOIN differentiation_profiles_adjustment_category ac ON dp.adjustment_category = ac.id
                  LEFT JOIN differentiation_profiles_adjustment_level al ON dp.adjustment_level = al.id
        ";

        if (count($wheres) > 0) {
            $query .= ' WHERE (' . implode(') AND (', $wheres) . ')';
        }

        return [$query, $params];
    }
}
