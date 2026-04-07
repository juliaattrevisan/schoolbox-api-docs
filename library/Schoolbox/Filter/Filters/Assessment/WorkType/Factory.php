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
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
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
     * @param Access                             $access
     * @param FolderMapper                       $folderMapper
     */
    public function __construct(
        Filter\Page\Option\Mapper $optionMapper,
        Filter\Page\Option\Defaults\Mapper $defaultMapper,
        Filter\Selection\Mapper $selectionMapper,
        private WorkType\Mapper $workTypeMapper,
        private Access $access,
        private FolderMapper $folderMapper,
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
     * @param User|null        $viewee
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
        if (
            Filter\Page\Model::PAGE_UPCOMING_ASSESSMENTS === $pageFilter->getPage()
            || Filter\Page\Model::PAGE_GRADES === $pageFilter->getPage()
        ) {
            $workTypes = $this->classifyViewerWorkTypes($user, $viewee);
        } else {
            /* Everything else is a staff-only page */
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

    /**
     * Classifies the viewer's relationship to the viewee and returns the appropriate set of visible work types.
     *
     * @param User      $user   The viewer
     * @param User|null $viewee The user whose assessments are being viewed
     * @return GenericCollection|WorkType\Model[]
     * @throws InvalidColumnException
     */
    private function classifyViewerWorkTypes(User $user, ?User $viewee): GenericCollection
    {
        // Case 1: viewer is looking at their own page — treat as learner
        if ($viewee === null || $user->getId() === $viewee->getId()) {
            return $this->workTypeMapper->findByPredicates(
                $this->workTypeMapper->getPredicateBuilder()->notEquals('is_visible_to_learners', 0)
            );
        }

        // Case 2: viewer is a parent of the viewee
        foreach ($user->getChildren() ?? [] as $child) {
            if ($child->getId() === $viewee->getId()) {
                return $this->workTypeMapper->findByPredicates(
                    $this->workTypeMapper->getPredicateBuilder()->notEquals('is_visible_to_parents', 0)
                );
            }
        }

        // Case 3: determine viewer's folder relationship to the viewee
        $maxAccess = $this->getMaxViewerAccessToVieweeFolders($user, $viewee);

        if ($maxAccess >= UserAccess::ACCESS_WRITE) {
            // Viewer is an instructor in one of the viewee's folders
            return $this->workTypeMapper->findAll();
        }

        if ($maxAccess === UserAccess::ACCESS_READ) {
            // Viewer is also a learner in one of the viewee's folders
            return $this->workTypeMapper->findByPredicates(
                $this->workTypeMapper->getPredicateBuilder()->notEquals('is_visible_to_learners', 0)
            );
        }

        if ($user->getRole()->isStaff()) {
            // Staff with no folder relationship to the viewee — still gets all work types
            return $this->workTypeMapper->findAll();
        }

        // No relevant relationship — empty collection
        return new GenericCollection(WorkType\Model::class);
    }

    /**
     * Returns the highest folder access level the viewer has across all folders where the viewee is a learner (READ access).
     *
     * @param User $viewer
     * @param User $viewee
     * @return int One of UserAccess::ACCESS_DENY, ACCESS_READ, or ACCESS_WRITE+
     */
    private function getMaxViewerAccessToVieweeFolders(User $viewer, User $viewee): int
    {
        $vieweeFolderIds = $this->folderMapper->findFolderIdsForUser(
            $viewee,
            true,                      // include subjects
            UserAccess::ACCESS_READ,   // only folders where viewee is a learner
            true                       // exactAccess: exclude WRITE/FULL folders (those make viewee an instructor, not learner)
        );

        if (empty($vieweeFolderIds)) {
            return UserAccess::ACCESS_DENY;
        }

        $maxAccess = UserAccess::ACCESS_DENY;
        foreach ($vieweeFolderIds as $folderId) {
            $access = $this->access->getFolderAccess($viewer, $folderId);
            if ($access > $maxAccess) {
                $maxAccess = $access;
            }
            if ($maxAccess >= UserAccess::ACCESS_WRITE) {
                break; // Instructor access is the highest we care about
            }
        }
        return $maxAccess;
    }
}
