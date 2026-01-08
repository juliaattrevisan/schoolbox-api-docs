<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\DifferentiationProfile;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractIdFinder;
use Schoolbox\GenericCollection;
use Schoolbox\DifferentiationProfile\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\DifferentiationProfile\Model;

/**
 * Finder for the differentiation profile filter.
 */
class Finder extends AbstractIdFinder
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
     * @param array      $filters
     * @param array|null $cursor
     * @param array|null $order
     * @param int|null   $limit
     * @return Collection|GenericCollection|Model[]
     */
    public function findProfilesForStudentsByFilters(
        User $viewer,
        array $filters,
        ?array $cursor,
        ?array $order,
        ?int $limit
    ): Collection|array|GenericCollection {
        // Ensure all filter keys so we don't need to perform repeated isset() checks
        $completeFilters = $this->normaliseFilters($filters);

        // Can we conclude filters will never permit any assessments to match? ie always an empty set?
        if ($this->doFiltersGuaranteeEmptySet($completeFilters)) {
            return new Collection();
        }

        // Can we simplify filters using set theory?
        $completeFilters = $this->simplifyFilters($completeFilters);

        $studentName = $completeFilters[Name\Definition::class]?->getAll();

        $diffProfileCampuses = $completeFilters[Campus\Definition::class]?->getAll();

        $diffProfileYearLevels = $completeFilters[YearLevel\Definition::class]?->getAll();

        $diffProfileAdjustmentCategories = $completeFilters[AdjustmentCategory\Definition::class]?->getAll();

        $diffProfileAdjustmentLevels = $completeFilters[AdjustmentLevel\Definition::class]?->getAll();

        $diffProfileStatus = $completeFilters[Status\Definition::class]?->getAll();

        /** @var Mapper $mapper */
        $mapper = $this->getMapper();
        return $mapper->findByFilters(
            $viewer,
            $studentName,
            $diffProfileYearLevels,
            $diffProfileCampuses,
            $diffProfileAdjustmentCategories,
            $diffProfileAdjustmentLevels,
            $diffProfileStatus,
            $cursor,
            $order,
            $limit
        );
    }

    /**
     * @inheritDoc
     */
    protected function buildQuery(array $completeFilters, User $user, bool $deleted): array
    {
        return [];
    }
}
