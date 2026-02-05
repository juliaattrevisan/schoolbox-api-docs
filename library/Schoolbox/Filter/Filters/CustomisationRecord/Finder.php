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

        $customisationRecordCampuses = $completeFilters[Campus\Definition::class]?->getAll();

        $customisationRecordYearLevels = $completeFilters[YearLevel\Definition::class]?->getAll();

        $customisationRecordAdjustmentCategories = $completeFilters[AdjustmentCategory\Definition::class]?->getAll();

        $customisationRecordAdjustmentLevels = $completeFilters[AdjustmentLevel\Definition::class]?->getAll();

        $customisationRecordTerm = $completeFilters[Term\Definition::class]?->getAll();

        /** @var Mapper $mapper */
        $mapper = $this->getMapper();
        return $mapper->findByFilters(
            $viewer,
            $studentName,
            $customisationRecordYearLevels,
            $customisationRecordCampuses,
            $customisationRecordAdjustmentCategories,
            $customisationRecordAdjustmentLevels,
            $customisationRecordTerm,
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
