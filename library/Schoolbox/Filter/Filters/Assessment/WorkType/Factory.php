<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\WorkType;

use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\IComponent;
use Schoolbox\Filter\IDefinition;
use Schoolbox\Filter\Page\Model;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\User;

/**
 * A factory for building 'WorkType' filter objects
 */
class Factory extends Filter\AbstractFactory
{
    /**
     * @param Filter\Page\Option\Mapper          $optionMapper
     * @param Filter\Page\Option\Defaults\Mapper $defaultMapper
     * @param Filter\Selection\Mapper            $selectionMapper
     * @param WorkType\Mapper                    $workTypeMapper
     */
    public function __construct(
        Filter\Page\Option\Mapper $optionMapper,
        Filter\Page\Option\Defaults\Mapper $defaultMapper,
        Filter\Selection\Mapper $selectionMapper,
        private WorkType\Mapper $workTypeMapper
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Definition|Filter\IDefinition
     */
    public function buildDefinition(Filter\Model $filter): Filter\IDefinition
    {
        return new Definition(
            $filter,
            $this->getOptionMapper(),
            $this->getDefaultsMapper(),
            $this->workTypeMapper->findAll()
        );
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        return new KeyConverter($filter, new Collection($this->workTypeMapper->findAllIds()));
    }


    /**
     * @inheritDoc
     * @param IDefinition $definition
     * @param User $user
     * @param Model $pageFilter
     * @param GenericCollection|null $selections
     * @param User|null $viewee
     * @param array $routeArgs
     * @param GenericCollection $overrides
     * @return IComponent
     * @throws InvalidColumnException
     * @throws \InvalidArgumentException If neither $selections or $overrides are supplied
     */
    public function buildComponent(
        IDefinition $definition,
        User $user,
        \Schoolbox\Filter\Page\Model $pageFilter,
        ?GenericCollection $selections,
        ?User $viewee,
        array $routeArgs,
        GenericCollection $overrides
    ): IComponent {
        /* lol...nobody can see a SIVO assessment:
        *
         *     - Students can never see a SIVO assessment in their due work calendar
         *
          *    - Staff looking at a student's due work calendar shows what the student would have seen,
         *       hiding SIVO assessments from staff by sheer circumstance.
         *
         * ...but there are numerous other contexts (such as markbooks) where SIVO assessments
         * need to be visible to $user based on their role type.
         */
        if (Filter\Page\Model::PAGE_UPCOMING_ASSESSMENTS === $pageFilter->getPage()) {
            /* Due Work calendar: never see a SIVO regardless if you're a staff or a student.
             */
            $workTypes = $this->workTypeMapper->findByPredicates($this->workTypeMapper->getPredicateBuilder()->equals('staff_or_instructor_visible_only', 0));
        } elseif (Filter\Page\Model::PAGE_GRADES === $pageFilter->getPage()) {
            /* Grades page: role dependent
             */
            if ($user->getRole()->isStaff()) {
                $workTypes = $this->workTypeMapper->findAll();
            } else {
                $workTypes = $this->workTypeMapper->findByPredicates($this->workTypeMapper->getPredicateBuilder()->equals('staff_or_instructor_visible_only', 0));
            }
        } else {
            /* Everything else is a staff-only page
             */
            $workTypes = $this->workTypeMapper->findAll();
        }

        return new Component(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $overrides[$pageFilter->getFilter()->getId()],
            $this->getSelectionMapper(),
            $workTypes
        );
    }
}
