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
class Component extends Filter\AbstractComponent
{

    /**
     * @var GenericCollection|CourseSpec\Model[]
     */
    private $courseSpecs;

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
     * @param Filter\IDefinition                         $definition
     * @param User                                       $user
     * @param Filter\Page\Model                          $pageFilter
     * @param GenericCollection|Filter\Selection\Model[] $userSelections
     * @param Filter\Selection\Mapper                    $filterSelectionMapper
     * @param GenericCollection|UnitSpec\Model[]         $unitSpecsImportedToClasses
     * @param GenericCollection|UnitSpec\Model[]         $unitSpecsImportedToGroups
     */
    public function __construct(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        GenericCollection $userSelections,
        Filter\Selection\Mapper $filterSelectionMapper,
        GenericCollection $unitSpecsImportedToClasses,
        GenericCollection $unitSpecsImportedToGroups
    ) {
        parent::__construct($definition, $user, $pageFilter, $userSelections, $filterSelectionMapper);
        $this->unitSpecsImportedToClasses = $unitSpecsImportedToClasses;
        $this->unitSpecsImportedToGroups  = $unitSpecsImportedToGroups;

        /** @var UnitSpec\Model[] $unitSpecs */
        $unitSpecs         = $unitSpecsImportedToClasses->mergeUnique($unitSpecsImportedToGroups, 'id');
        $this->courseSpecs = CourseSpec\Mapper::getInstance()->createCollection();

        // Build indexed collection of course specs
        foreach ($unitSpecs as $unitSpec) {
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
        foreach ($unitSpecs as $unitSpec) {
            $unitSpec->getCourse()->getUnits()->setOffset($unitSpec->getId(), $unitSpec);
        }

        // Sort unitSpecs inside courseSpecs by unitSpec.folder.name
        foreach ($this->courseSpecs as $courseSpec) {
            $courseSpec->getUnits()->sortBy('title', 'ASC', null, true);
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

        foreach ($abstractOptions as $option) {

            if(!$option->getAvailable()){
                continue;
            }

            $selected = $option->getDefaults()[$roleType]->getSelected();

            // Is this the 'On Folder' abstract option?
            if (Definition::KEY_NONE === $option->getKey()) {
                $key                       = $option->getKey();
                $availableSelections[$key] = $this->createSelection($key, $selected);
            } elseif (Definition::KEY_IMPORTED_TO_CLASS === $option->getKey()) {
                foreach ($this->unitSpecsImportedToClasses as $unitSpec) {
                    $key                       = (string)$unitSpec->getId();
                    $availableSelections[$key] = $this->createSelection($key, $selected);
                }
            } elseif (Definition::KEY_IMPORTED_TO_GROUP === $option->getKey()) {
                foreach ($this->unitSpecsImportedToGroups as $unitSpec) {
                    $key = (string)$unitSpec->getId();

                    if (!$availableSelections->offsetExists($key)) {
                        // Does the unit spec not yet exist in selections ?
                        $availableSelections[$key] = $this->createSelection($key, $selected);
                    } elseif ($selected) {
                        // Does it exist, ensure it is selected if abstract option is selected
                        $availableSelections[$key]->setSelected(true);
                    }
                }
            } else {
                throw new \InvalidArgumentException("Unknown option key: {$option->getKey()}");
            }
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

}
