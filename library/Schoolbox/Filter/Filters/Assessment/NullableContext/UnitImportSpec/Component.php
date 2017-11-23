<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\UnitImportSpec;

use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;

/**
 * @method Definition getDefinition()
 */
class Component extends Filter\BaseComponent
{

    /**
     * @var GenericCollection|CourseSpec\Model[]
     */
    private $courseSpecs;

    /**
     * UnitSpecs imported to folders
     * (we need this because injected folders may be neither classes or groups)
     *
     * @var GenericCollection|UnitSpec\Model[]
     */
    private $unitSpecsImportedToFolders;

    /**
     * UnitSpecs imported to classes
     *
     * @var GenericCollection|UnitSpec\Model[]
     */
    private $unitSpecsImportedToClasses;

    /**
     * UnitSpecs imported to groups
     *
     * @var GenericCollection|UnitSpec\Model[]
     */
    private $unitSpecsImportedToGroups;


    /**
     * Note that it is possible to inject a folder that is neither a class not group.
     * In this case, it will most likely clobber user-selections, so don't select the units
     * imported to that injected folder so the unit filter can be nulled.
     *
     * @param Filter\IDefinition                         $definition
     * @param User                                       $user
     * @param Filter\Page\Model                          $pageFilter
     * @param GenericCollection|Filter\Selection\Model[] $userSelections
     * @param Filter\Selection\Mapper                    $filterSelectionMapper
     * @param GenericCollection|UnitSpec\Model[]         $unitSpecsImportedToFolders - the full set
     * @param GenericCollection|UnitSpec\Model[]         $unitSpecsImportedToClasses - to classes
     * @param GenericCollection|UnitSpec\Model[]         $unitSpecsImportedToGroups  - to groups
     */
    public function __construct(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        GenericCollection $userSelections,
        Filter\Selection\Mapper $filterSelectionMapper,
        GenericCollection $unitSpecsImportedToFolders,
        GenericCollection $unitSpecsImportedToClasses,
        GenericCollection $unitSpecsImportedToGroups
    ) {
        parent::__construct($definition, $user, $pageFilter, $userSelections, $filterSelectionMapper);
        // (for injected folders that are neither classes or groups)
        $this->unitSpecsImportedToFolders = $unitSpecsImportedToFolders;
        $this->unitSpecsImportedToClasses = $unitSpecsImportedToClasses;
        $this->unitSpecsImportedToGroups  = $unitSpecsImportedToGroups;
        $this->courseSpecs                = CourseSpec\Mapper::getInstance()->createCollection();

        // Build indexed collection of course specs
        foreach ($unitSpecsImportedToFolders as $unitSpec) {
            $courseSpec = $unitSpec->getCourse();
            if (!$this->courseSpecs->offsetExists($courseSpec->getId())) {
                $this->courseSpecs->setOffset($courseSpec->getId(), $courseSpec);
            }
        }

        // Ensure course unit lists are empty (eg, we may be only interested in class units, but they already contain group units)
        foreach ($this->courseSpecs as $courseSpec) {
            $courseSpec->setUnits($courseSpec->getUnits()->emptyClone());
        }

        // Add unitSpecs to courseSpecs
        foreach ($unitSpecsImportedToFolders as $unitSpec) {
            $unitSpec->getCourse()->getUnits()->setOffset($unitSpec->getId(), $unitSpec);
        }

        // Sort unitSpecs inside courseSpecs by unitSpec.id
        foreach ($this->courseSpecs as $courseSpec) {
            $courseSpec->getUnits()->sortBy('id', 'ASC', null, true);
        }

        // Sort Courses by title
        $this->courseSpecs->sortBy('title', 'ASC', null, true);
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

        // The '(none)' abstract-option maps to a single '(none)' concrete-option. Should it be available?
        if ($abstractOptions[Definition::KEY_NONE]->getAvailable()) {
            $selected                                  = $abstractOptions[Definition::KEY_NONE]->getDefaults()[$roleType]->getSelected();
            $availableSelections[Definition::KEY_NONE] = $this->createSelection(Definition::KEY_NONE, $selected);
        }

        // Should units imported to classes be available?
        if (!$isUserConfigurable || $abstractOptions[Definition::KEY_IMPORTED_TO_CLASS]->getAvailable()) {
            $availableSelections = $this->insertSelections(
                $availableSelections,
                $this->unitSpecsImportedToClasses,
                $abstractOptions[Definition::KEY_IMPORTED_TO_CLASS]->getDefaults()[$roleType]->getSelected()
            );
        }

        // Should units imported to groups be available?
        if (!$isUserConfigurable || $abstractOptions[Definition::KEY_IMPORTED_TO_GROUP]->getAvailable()) {
            $availableSelections = $this->insertSelections(
                $availableSelections,
                $this->unitSpecsImportedToGroups,
                $abstractOptions[Definition::KEY_IMPORTED_TO_GROUP]->getDefaults()[$roleType]->getSelected()
            );
        }

        // The system may have injected folders into the filter panel that are neither classes nor groups.
        // In this case any units imported to these folders will be added yet. We can also safely assume in
        // this scenario that no options should be selected because overrides will take care of it for us.
        foreach ($this->unitSpecsImportedToFolders as $unitSpecId => $unitSpec) {
            // Already processed?
            if ($this->unitSpecsImportedToClasses[$unitSpecId] || $this->unitSpecsImportedToGroups[$unitSpecId]) {
                continue;
            }
            // Unit's folder must have been injected, so ensure this unit is available.
            $availableSelections[$unitSpecId] = $this->createSelection($unitSpecId, false);
        }
        return $availableSelections;
    }


    /**
     * @inheritdoc
     */
    public function toContext()
    {
        return array_merge(parent::toContext(), [
            'courseSpecs' => $this->courseSpecs,
            'keyNone'     => Definition::KEY_NONE,
        ]);
    }


    /**
     * Convert the unitSpecs into selections, and insert into available selection collection.
     *
     * @param GenericCollection|Filter\Selection\Model[] $availableSelections
     * @param GenericCollection|UnitSpec\Model[]         $unitSpecs
     * @param bool                                       $selected
     * @return GenericCollection
     */
    private function insertSelections(
        GenericCollection $availableSelections,
        GenericCollection $unitSpecs,
        bool $selected
    ): GenericCollection {
        foreach ($unitSpecs as $unitSpec) {
            $key = (string)$unitSpec->getId();
            if (!$availableSelections->keyExists($key)) {
                // Doesn't exist?
                $availableSelections[$key] = $this->createSelection($key, $selected);
            } elseif ($selected) {
                // exists and should be selected?
                $availableSelections[$key]->setSelected(true);
            }
        }
        return $availableSelections;
    }
}
