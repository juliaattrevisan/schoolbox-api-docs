<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Folder;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractFilterComponent;
use Schoolbox\Filter\IFilterDefinition;
use Schoolbox\Filter\Selection;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use Schoolbox\Filter\Option;

/**
 * @method Definition getDefinition()
 */
class Component extends AbstractFilterComponent
{

    /**
     * @var GenericCollection|Folder\Model[]
     */
    private $classes;

    /**
     * @var GenericCollection|Folder\Model[]
     */
    private $groups;


    /**
     * @param IFilterDefinition                           $definition
     * @param User                                        $user
     * @param string                                      $page
     * @param GenericCollection|Selection\Model[]         $savedSelections
     * @param Selection\Mapper                            $filterSelectionMapper
     * @param Collection|GenericCollection|Folder\Model[] $classes
     * @param Collection|GenericCollection|Folder\Model[] $groups
     */
    public function __construct(
        IFilterDefinition $definition,
        User $user,
        $page,
        GenericCollection $savedSelections,
        Selection\Mapper $filterSelectionMapper,
        GenericCollection $classes,
        GenericCollection $groups
    ) {
        parent::__construct($definition, $user, $page, $savedSelections, $filterSelectionMapper);
        $this->classes = $classes;
        $this->groups  = $groups;
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

        foreach ($optionDefaults as $option) {
            $selected = $option->getDefaults()[$roleType]->getSelected();

            if (Definition::KEY_CLASSES === $option->getKey()) {
                foreach ($this->classes as $folder) {
                    $key                       = (string)$folder->getId();
                    $availableSelections[$key] = $this->createSelection($key, $selected);
                }
            } elseif (Definition::KEY_GROUPS === $option->getKey()) {
                foreach ($this->groups as $folder) {
                    $key = (string)$folder->getId();

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
            'classes' => $this->classes,
            'groups'  => $this->groups,
        ]);
    }
}
