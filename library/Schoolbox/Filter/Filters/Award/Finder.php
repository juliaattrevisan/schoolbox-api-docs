<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Award;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractIdFinder;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Badge\Award\Mapper;
use Schoolbox\Learning\Badge\Award\Model;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;

/**
 * ID finder for the badge filter.
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
     * @param array $studentIds
     * @param array $filters
     * @return Collection|GenericCollection|Model[]
     * @throws MapperException
     */
    public function findListForStudentsByFilters(array $studentIds, array $filters = [])
    {
        // Ensure all filter keys so we don't need to perform repeated isset() checks
        $completeFilters = $this->normaliseFilters($filters);

        // Can we conclude filters will never permit any assessments to match? ie always an empty set?
        if ($this->doFiltersGuaranteeEmptySet($completeFilters)) {
            return new Collection();
        }

        // Can we simplify filters using set theory?
        $completeFilters = $this->simplifyFilters($completeFilters);

        // Badge Categories
        $categoryIds = $completeFilters[Category\Definition::class] instanceof Collection
            ? $completeFilters[Category\Definition::class]->getAll() : [];

        // Badge Status
        $status = $completeFilters[Status\Definition::class] instanceof Collection
            ? $completeFilters[Status\Definition::class]->getAll() : [];

        // Term
        $termSelections = $completeFilters[Term\Definition::class] instanceof Collection
            ? $completeFilters[Term\Definition::class]->getAll() : [];
        /** @var Mapper $mapper */
        $mapper = $this->getMapper();
        return $mapper->findForUsers($studentIds, $categoryIds, $status, $termSelections);
    }

    /**
     * @inheritDoc
     */
    protected function buildQuery(array $completeFilters, User $user, bool $deleted): array
    {
        return [];
    }
}
