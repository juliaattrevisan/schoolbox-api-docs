<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\CustomisationRecord;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractIdFinder;
use Schoolbox\GenericCollection;
use Schoolbox\CustomisationRecord\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\Assessment\Participant\Model;

/**
 * Finder for the customisation record filter.
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
    public function findCustomisationForStudentsByFilters(
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

        /** @var Mapper $mapper */
        $mapper = $this->getMapper();
        [
            $studentName,
            $customisationRecordYearLevels,
            $customisationRecordCampuses,
            $customisationRecordAdjustmentCategories,
            $customisationRecordAdjustmentLevels,
            $customisationRecordTerm,
            $customisationRecordWorkTypes,
        ] = $this->getFilterValues($completeFilters);

        return $mapper->findByFilters(
            $studentName,
            $customisationRecordYearLevels,
            $customisationRecordCampuses,
            $customisationRecordAdjustmentCategories,
            $customisationRecordAdjustmentLevels,
            $customisationRecordTerm,
            $customisationRecordWorkTypes,
            $cursor,
            $order,
            $limit
        );
    }

    public function countCustomisationForStudentsByFilters(array $filters): int
    {
        // Ensure all filter keys so we don't need to perform repeated isset() checks
        $completeFilters = $this->normaliseFilters($filters);

        // Can we conclude filters will never permit any assessments to match? ie always an empty set?
        if ($this->doFiltersGuaranteeEmptySet($completeFilters)) {
            return 0;
        }

        // Can we simplify filters using set theory?
        $completeFilters = $this->simplifyFilters($completeFilters);

        /** @var Mapper $mapper */
        $mapper = $this->getMapper();
        return $mapper->countByFilters(
            ...$this->getFilterValues($completeFilters),
        );
    }

    private function getFilterValues(array $completeFilters): array
    {
        return [
            $completeFilters[Name\Definition::class]?->getAll(),
            $completeFilters[YearLevel\Definition::class]?->getAll(),
            $completeFilters[Campus\Definition::class]?->getAll(),
            $completeFilters[AdjustmentCategory\Definition::class]?->getAll(),
            $completeFilters[AdjustmentLevel\Definition::class]?->getAll(),
            $completeFilters[Term\Definition::class]?->getAll(),
            $completeFilters[WorkType\Definition::class]?->getAll(),
        ];
    }

    /**
     * @inheritDoc
     */
    protected function buildQuery(array $completeFilters, User $user, bool $deleted): array
    {
        return [];
    }
}
