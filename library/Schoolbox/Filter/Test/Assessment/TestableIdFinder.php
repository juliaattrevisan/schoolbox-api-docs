<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Test\Assessment;

use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\IdFinder;
use Schoolbox\IMapper;
use Schoolbox\User;
use UnderflowException;
use UnexpectedValueException;
use Schoolbox\Filter\Filters\Assessment;

/**
 * Enables unit testing of the parent's logic by exposing internal methods.
 *
 * @method static TestableIdFinder getInstance(\Schoolbox\Filter\Mapper $filterMapper, \Schoolbox\IMapper $mapper)
 */
class TestableIdFinder extends IdFinder
{
    /**
     * @param array $completeFilters
     * @return bool
     */
    public function getFiltersGuaranteeEmptySet(array $completeFilters): bool
    {
        return $this->doFiltersGuaranteeEmptySet($completeFilters);
    }

    /**
     * @param array $completeFilters
     * @return bool
     */
    public function getDoContextFiltersGuaranteeEmptySet(array $completeFilters): bool
    {
        return $this->doContextFiltersGuaranteeEmptySet($completeFilters);
    }

    /**
     * @param array $completeFilters
     * @return bool
     */
    public function getDoNonContextFiltersGuaranteeEmptySet(array $completeFilters): bool
    {
        return $this->doNonContextFiltersGuaranteeEmptySet($completeFilters);
    }


    /**
     * @param int[][]|null[] $completeFilters All keys must exists (to avoid needing a heap of isset() calls)
     * @return mixed[] UNION sub-query string + params array
     * @throws Filter\BrokenFilterException If filter SQL fragment cannot be built
     * @throws InvalidColumnException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    public function getSqlStringForFilters(array $completeFilters): array
    {
        return $this->buildQuery($completeFilters, new User(), false);
    }


    /**
     * Inserts null values for missing filters and ensures any scalars are wrapped in Collections
     *
     * @param array $filters
     * @return Collection[]|null[]|mixed[][]
     * @throws Filter\BrokenFilterException
     */
    public function insertMissingFilters($filters): array
    {
        return $this->normaliseFilters($filters);
    }


    /**
     * @inheritDoc
     */
    public function simplifyFilters(array $completeFilters): array
    {
        return parent::simplifyFilters($completeFilters);
    }


    /**
     * Build a registry of filters known to this class
     *
     * We need to override this for the testable version as we cannot access the database dependency in unit tests
     *
     * @param Filter\Mapper $filterMapper The filter instance mapper
     * @param IMapper       $entityMapper A mapper for the entity being filtered, eg 'assessments'
     */
    protected function buildRegistry(Filter\Mapper $filterMapper, IMapper $entityMapper): void
    {
        $filterClasses = [
            Assessment\AssessmentType\Definition::class,
            Assessment\Folder\TeacherInvolved\Definition::class,
            Assessment\Folder\StudentInteractions\Definition::class,
            Assessment\Folder\Readable\Definition::class,
            Assessment\Folder\CourseImports\Definition::class,
            Assessment\NullableContext\CourseImportSpec\Definition::class,
            Assessment\NullableContext\UnitImportSpec\Definition::class,
            Assessment\NullableContext\Project\Definition::class,
            Assessment\NullableContext\UnitImpl\Definition::class,
            Assessment\NullableContext\UnitSpec\Definition::class,
            Assessment\TemporalState\Definition::class,
            Assessment\Term\Due\Definition::class,
            Assessment\Term\Close\Definition::class,
            Assessment\WeightingType\Definition::class,
            Assessment\WorkType\Definition::class,
            Assessment\YearDue\Definition::class,
            Assessment\RubricUsage\Definition::class,
            Assessment\MarkPerCap\Definition::class,
            Assessment\FolderYearLevel\Definition::class,
        ];
        $this->setRegistry(array_combine($filterClasses, $filterClasses));
    }
}
