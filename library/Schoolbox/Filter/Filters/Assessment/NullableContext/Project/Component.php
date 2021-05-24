<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\Project;

use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\Project;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;

/**
 * @method Definition getDefinition()
 */
class Component extends Filter\BaseComponent
{

    /**
     * Projects that reside on options set as available in admin, and are linked
     * to current user's folders.
     *
     * @var GenericCollection|Project\Model[]
     */
    private $projects;


    /**
     * @param Filter\IDefinition                              $definition
     * @param User                                            $user
     * @param Filter\Page\Model                               $pageFilter
     * @param null|GenericCollection|Filter\Selection\Model[] $userSelections
     * @param null|GenericCollection|Filter\Selection\Model[] $urlOverrides
     * @param Filter\Selection\Mapper                         $filterSelectionMapper
     * @param GenericCollection|Project\Model[]               $projects
     * @throws \InvalidArgumentException If neither $userSelections or $urlOverrides are supplied
     */
    public function __construct(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        ?GenericCollection $userSelections,
        ?GenericCollection $urlOverrides,
        Filter\Selection\Mapper $filterSelectionMapper,
        GenericCollection $projects
    ) {
        parent::__construct($definition, $user, $pageFilter, $userSelections, $urlOverrides, $filterSelectionMapper);
        $this->projects = $projects;
    }


    /**
     * @inheritdoc
     */
    public function toContext()
    {
        return array_merge(parent::toContext(), [
            'projects' => $this->projects,
            'keyNone'  => Definition::KEY_NONE,
        ]);
    }


    /**
     * @inheritdoc
     * @param GenericCollection|Filter\Page\Option\Model[] $abstractOptions $optionDefaults
     * @throws \InvalidArgumentException If option key unrecognised
     */
    protected function buildRoleSelections(GenericCollection $abstractOptions)
    {
        $availableSelections = $this->getFilterSelectionMapper()->createCollection();
        $roleType            = $this->getUser()->getRole()->getType();
        $isUserConfigurable  = $this->getPageFilter()->getUserConfigurable();

        // Insert the (none) option
        $key = Definition::KEY_NONE;
        if (!$isUserConfigurable || $abstractOptions[$key]->getAvailable()) {
            $isSelected                = $abstractOptions[$key]->getDefaults()[$roleType]->isSelected();
            $availableSelections[$key] = $this->createSelection($key, $isSelected);
        }

        // A project will be available if it matches any abstract key. (only available projects were injected from factory)
        // A project will be checked if any matching abstract project defaults were checked

        // TODO ensure the factory ignores $option.isAvailable if $pageFilter.userConfigurable == false
        foreach ($this->projects as $project) {
            $key                       = (string)$project->getId();
            $availableSelections[$key] = $this->createSelection($key, $this->isSelected($project, $abstractOptions, $roleType));
        }
        return $availableSelections;
    }


    /**
     * Should the specified Project be checked in list of available options for the user's role?
     *
     * @param Project\Model                                $project
     * @param GenericCollection|Filter\Page\Option\Model[] $optionDefaults
     * @param string                                       $roleType
     * @return bool
     */
    private function isSelected(Project\Model $project, GenericCollection $optionDefaults, $roleType)
    {
        $context = $project->getAssessmentModel()->getAssessmentContext();
        $folder  = $project->getFolder();

        $onFolder   = $context instanceof Folder\Model;
        $inUnitImpl = $context instanceof UnitImpl\Model;

        $onClassFolder   = $onFolder && $folder->getCodes()->count() > 0;
        $onGroupFolder   = $onFolder && $folder->getGroup();
        $onClassUnitImpl = $inUnitImpl && $folder->getCodes()->count() > 0;
        $onGroupUnitImpl = $inUnitImpl && $folder->getGroup();

        if (
            $onClassFolder && $optionDefaults->offsetExists(Definition::KEY_ON_CLASS_FOLDER)
            && $optionDefaults[Definition::KEY_ON_CLASS_FOLDER]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        if (
            $onGroupFolder && $optionDefaults->offsetExists(Definition::KEY_ON_GROUP_FOLDER)
            && $optionDefaults[Definition::KEY_ON_GROUP_FOLDER]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        if (
            $onClassUnitImpl && $optionDefaults->offsetExists(Definition::KEY_ON_CLASS_UNIT_IMPL)
            && $optionDefaults[Definition::KEY_ON_GROUP_FOLDER]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        if (
            $onGroupUnitImpl && $optionDefaults->offsetExists(Definition::KEY_ON_GROUP_UNIT_IMPL)
            && $optionDefaults[Definition::KEY_ON_GROUP_FOLDER]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        return false;
    }
}
