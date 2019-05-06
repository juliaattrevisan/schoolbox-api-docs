<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment;

use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\IMapper;
use UnderflowException;
use UnexpectedValueException;

/**
 * Enables unit testing of the parent's logic by exposing internal methods.
 */
class TestableIdFinder extends IdFinder
{

    /**
     * @param array $completeFilters
     * @return bool
     */
    public function getFiltersGuaranteeEmptySet(array $completeFilters)
    {
        return $this->doFiltersGuaranteeEmptySet($completeFilters);
    }

    /**
     * @param array $completeFilters
     * @return bool
     */
    public function getDoContextFiltersGuaranteeEmptySet(array $completeFilters)
    {
        return $this->doContextFiltersGuaranteeEmptySet($completeFilters);
    }

    /**
     * @param array $completeFilters
     * @return bool
     */
    public function getDoNonContextFiltersGuaranteeEmptySet(array $completeFilters)
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
    public function getSqlStringForFilters(array $completeFilters)
    {
        return $this->buildQuery($completeFilters, false);
    }


    /**
     * Inserts null values for missing filters and ensures any scalars are wrapped in Collections
     *
     * @param $filters
     * @return Collection[]|null[]|mixed[][]
     * @throws Filter\BrokenFilterException
     */
    public function insertMissingFilters($filters)
    {
        return $this->normaliseFilters($filters);
    }

    /**
     * Build a registry of filters known to this class
     *
     * We need to override this for the testable version as we cannot access the database dependency in unit tests
     *
     * @param Filter\Mapper $filterMapper The filter instance mapper
     * @param IMapper       $entityMapper A mapper for the entity being filtered, eg 'assessments'
     */
    protected function buildRegistry(Filter\Mapper $filterMapper, IMapper $entityMapper)
    {
        $filterClasses = [
            AssessmentType\Definition::class,
            Folder\TeacherInvolved\Definition::class,
            Folder\StudentInteractions\Definition::class,
            Folder\CourseImports\Definition::class,
            NullableContext\UnitImportSpec\Definition::class,
            NullableContext\Project\Definition::class,
            NullableContext\UnitImpl\Definition::class,
            NullableContext\UnitSpec\Definition::class,
            TemporalState\Definition::class,
            Term\Due\Definition::class,
            Term\Close\Definition::class,
            WeightingType\Definition::class,
            WorkType\Definition::class,
            YearDue\Definition::class,
        ];
        $this->setRegistry(array_combine($filterClasses, $filterClasses));
    }
}
