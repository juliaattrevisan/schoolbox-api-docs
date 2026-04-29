<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\WorkType;

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
     * @inheritdoc
     * @param IDefinition      $definition
     * @param User             $user
     * @param Model            $pageFilter
     * @param GenericCollection|null $selections
     * @param User|null $viewee
     * @param array            $routeArgs
     * @param GenericCollection $overrides
     * @return Component|IComponent
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
        return new Component(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $overrides[$pageFilter->getFilter()->getId()],
            $this->getSelectionMapper(),
            $this->getWorkTypesForPage($user, $pageFilter)
        );
    }

    /**
     * Returns a collection of work types required for filtering purposes on the given page.
     *
     * @param User  $user
     * @param Model $pageFilter
     * @return GenericCollection
     */
    private function getWorkTypesForPage(User $user, Model $pageFilter): GenericCollection
    {
        if (Filter\Page\Model::PAGE_UPCOMING_ASSESSMENTS === $pageFilter->getPage()) {
            // The Upcoming Assessments page is for either:
            //  * A parent viewing a child’s due work
            //  * A student viewing their own work
            //  * A staff member viewing a student’s due-work
            return $this->getLearnerOrParentVisibleWorkTypes($user);
        }

        if (Filter\Page\Model::PAGE_GRADES === $pageFilter->getPage()) {
            return $user->getRole()->isStaff()
                ? $this->workTypeMapper->findAll()
                : $this->getLearnerOrParentVisibleWorkTypes($user);
        }

        return $this->workTypeMapper->findAll();
    }

    /**
     * Retrieves the collection of work types visible to a learner or their parent
     * based on the user's role and specific visibility predicates.
     *
     * @param User $user The user whose role determines the visible work types.
     * @return GenericCollection The collection of work types visible to the user or their parent.
     */
    private function getLearnerOrParentVisibleWorkTypes(User $user): GenericCollection
    {
        return $user->getRole()->isParent()
            ? $this->workTypeMapper->findByPredicates(
                $this->workTypeMapper->getPredicateBuilder()->notEquals('is_visible_to_parents', 0)
            )
            : $this->workTypeMapper->findByPredicates(
                $this->workTypeMapper->getPredicateBuilder()->notEquals('is_visible_to_learners', 0)
            );
    }
}
