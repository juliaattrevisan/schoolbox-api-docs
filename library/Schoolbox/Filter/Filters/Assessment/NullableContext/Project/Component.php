<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\Project;

use Schoolbox\Filter\AbstractFilterComponent;
use Schoolbox\Filter\Filters\Assessment\NullableContext\Project\Definition;
use Schoolbox\Filter\IFilterDefinition;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\Project;
use Schoolbox\Filter\Selection;
use Schoolbox\User;
use Schoolbox\Filter\Option;
use Schoolbox\Resource\Folder;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;

/**
 * @method Definition getDefinition()
 */
class Component extends AbstractFilterComponent
{
    /**
     * Projects that reside on options set as available in admin, and are linked
     * to current user's folders.
     *
     * @var GenericCollection|Project\Model[]
     */
    private $projects;

    /**
     * @param IFilterDefinition                           $definition
     * @param User                                        $user
     * @param string                                      $page
     * @param null|GenericCollection|Selection\Model[]    $savedSelections
     * @param string[]|null                               $selectionOverrides
     * @param Selection\Mapper                            $filterSelectionMapper
     * @param GenericCollection|Project\Model[]           $projects
     *
     * @throws \DomainException If both saved selections and override selections are null
     */
    public function __construct(
        IFilterDefinition $definition,
        User $user,
        $page,
        GenericCollection $savedSelections = null,
        array $selectionOverrides = null,
        Selection\Mapper $filterSelectionMapper,
        GenericCollection $projects
    ) {
        parent::__construct($definition, $user, $page, $savedSelections, $selectionOverrides, $filterSelectionMapper);
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
     * Generate a list of available options, selected as per role defaults.
     *
     * Each option representing a concrete item (as opposed to an abstract concept in the admin system)
     *
     * @param GenericCollection|Option\Model[] $optionDefaults $optionDefaults
     * @return GenericCollection|Selection\Model[]
     * @throws \InvalidArgumentException If option key unrecognised
     */
    protected function getRoleSelections(GenericCollection $optionDefaults)
    {
        $availableSelections = $this->getFilterSelectionMapper()->createCollection();
        $roleType            = $this->getUser()->getRole()->getType();

        // Insert the (none) option
        $key = Definition::KEY_NONE;
        if($optionDefaults->offsetExists($key)){
            $isSelected = $optionDefaults[$key]->getDefaults()[$roleType]->isSelected();
            $availableSelections[$key] = $this->createSelection($key, $isSelected);
        }

        // A project will be available if it matches any abstract key. (only available projects were injected from factory)
        // A project will be checked if any matching abstract project defaults were checked

        foreach ($this->projects as $project) {
            $key                       = (string)$project->getId();
            $availableSelections[$key] = $this->createSelection($key, $this->isSelected($project, $optionDefaults, $roleType));
        }
        return $availableSelections;
    }


    /**
     * Should the specified Project be checked in list of available options for the user's role?
     *
     * @param Project\Model                    $project
     * @param GenericCollection|Option\Model[] $optionDefaults $optionDefaults
     * @param string                           $roleType
     * @return bool
     */
    private function isSelected(Project\Model $project, GenericCollection $optionDefaults, $roleType)
    {
        $context = $project->getAssessmentModel()->getAssessmentContext();
        $folder  = $project->getFolder();

        $onFolder = $context instanceof Folder\Model;
        $inUnitImpl = $context instanceof UnitImpl\Model;

        $onClassFolder = $onFolder && $folder->getCodes()->count() > 0;
        $onGroupFolder = $onFolder && $folder->getGroup();
        $onClassUnitImpl = $inUnitImpl && $folder->getCodes()->count() > 0;
        $onGroupUnitImpl = $inUnitImpl && $folder->getGroup();

        if ($onClassFolder && $optionDefaults->offsetExists(Definition::KEY_ON_CLASS_FOLDER)
            && $optionDefaults[Definition::KEY_ON_CLASS_FOLDER]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        if ($onGroupFolder && $optionDefaults->offsetExists(Definition::KEY_ON_GROUP_FOLDER)
            && $optionDefaults[Definition::KEY_ON_GROUP_FOLDER]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        if ($onClassUnitImpl && $optionDefaults->offsetExists(Definition::KEY_ON_CLASS_UNIT_IMPL)
            && $optionDefaults[Definition::KEY_ON_GROUP_FOLDER]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        if ($onGroupUnitImpl && $optionDefaults->offsetExists(Definition::KEY_ON_GROUP_UNIT_IMPL)
            && $optionDefaults[Definition::KEY_ON_GROUP_FOLDER]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        return false;
    }

}
