<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\ClassReport;

use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral;
use Schoolbox\User;

/**
 * Helper class for working with the filter on the class pastoral report page.
 */
class FilterHelper
{
    private const FILTER_PAGE = Filter\Page\Model::PAGE_CLASS_PASTORAL;

    /**
     * Constructor
     *
     * @param Pastoral\Mapper         $pastoralMapper
     * @param Filter\IFactoryFactory  $filterFactoryFactory
     * @param Filter\Mapper           $filterMapper
     * @param Filter\Selection\Mapper $filterSelectionMapper
     */
    public function __construct(
        private Pastoral\Mapper $pastoralMapper,
        private Filter\IFactoryFactory $filterFactoryFactory,
        private Filter\Mapper $filterMapper,
        private Filter\Selection\Mapper $filterSelectionMapper,
    ) {
    }

    /**
     * Build a filter panel for the current user and query string params.
     *
     * @param User  $currentUser
     * @param array $query
     * @return Filter\Panel
     */
    public function buildFilterPanel(User $currentUser, array $query): Filter\Panel
    {
        return $this->filterFactoryFactory->buildPanel(
            $currentUser,
            self::FILTER_PAGE,
            null,
            $currentUser,
            [],
            $query,
        );
    }

    /**
     * Update the selected filters for this user.
     *
     * @param User  $currentUser The request's user
     * @param array $query       The request's query string parameters
     * @return void
     * @throws MapperException if somehow the selections couldn't be updated
     */
    public function saveSelections(User $currentUser, array $query): void
    {
        $selectionsPerFilter = $this->buildSelectionPerFilter($currentUser, $query);
        $this->filterSelectionMapper->updateSelections(
            $currentUser,
            self::FILTER_PAGE,
            $selectionsPerFilter,
        );
    }

    /**
     * Find the pastoral records which match the filter and the set of students.
     *
     * @param User                     $currentUser The request's user
     * @param GenericCollection|User[] $students    The students to fetch
     *                                              records for
     * @param array $query                          The request's query string
     *                                              parameters
     * @return array
     */
    public function findPastoralRecordIds(User $currentUser, GenericCollection $students, array $query): array
    {
        // Piece together the actually selected filter
        $selectionsPerFilter = $this->buildSelectionPerFilter($currentUser, $query);
        $dynamicFilters      = $this->filterFactoryFactory->convertSelectionsToFilters(
            $selectionsPerFilter,
            $currentUser,
            self::FILTER_PAGE,
        );

        // Hardcoded filter: ensures only records for `$students` get returned
        $studentIds       = $students->getPropertyValues('id');
        $hardcodedFilters = [
            Filter\Filters\Pastoral\Student\Definition::class => array_combine($studentIds, $studentIds),
        ];

        // Find pastoral records
        $idFinder = Filter\Filters\Pastoral\IdFinder::getInstance(
            $this->filterMapper,
            $this->pastoralMapper,
        );
        return $idFinder->findIdsByFilters(
            array_merge($dynamicFilters, $hardcodedFilters),
            $currentUser,
        );
    }

    /**
     * Build filters out of the given selection
     *
     * @param User  $currentUser The request's user
     * @param array $query       The request's query string parameters
     * @return GenericCollection|GenericCollection[]|Filter\Selection\Model[][]
     */
    private function buildSelectionPerFilter(User $currentUser, array $query): GenericCollection
    {
        $request = $query['filters'] ?? [];
        $codec   = new Filter\Codec($this->filterMapper, $this->filterSelectionMapper);
        return $codec->convertRequestToSelections(
            $currentUser,
            self::FILTER_PAGE,
            $request,
        );
    }
}
