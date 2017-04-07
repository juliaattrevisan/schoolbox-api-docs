<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Folder;

use Schoolbox\Filter\AbstractFilterComponent;
use Schoolbox\Filter\IFilterDefinition;
use Schoolbox\Filter\Selection;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use Schoolbox\Mapper;
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
     * @param IFilterDefinition                $definition
     * @param User                             $user
     * @param string                           $page
     * @param GenericCollection                $savedSelections
     * @param Selection\Mapper                 $filterSelectionMapper
     * @param GenericCollection|Folder\Model[] $classes
     * @param GenericCollection|Folder\Model[] $groups
     */
    public function __construct(
        IFilterDefinition $definition,
        User $user,
        $page,
        GenericCollection $savedSelections,
        Selection\Mapper $filterSelectionMapper,
        GenericCollection $classes,
        GenericCollection $groups
    )
    {
        parent::__construct($definition, $user, $page, $savedSelections, $filterSelectionMapper);
        $this->classes = $classes;
        $this->groups  = $groups;
    }


    /**
     * @param Selection\Model|null             $abstractSelection
     * @param GenericCollection|Folder\Model[] $concreteFolders
     * @return Selection\Model[]|GenericCollection
     */
    protected function getConcreteSelections(Selection\Model $abstractSelection = null, GenericCollection $concreteFolders)
    {
        $availableSelections = $this->getFilterSelectionMapper()->createCollection();

        if(!$abstractSelection) {
            return $availableSelections;
        }

        foreach($concreteFolders as $classFolder) {
            $concreteSelection = $this->getFilterSelectionMapper()->createModel();
            $concreteSelection
                ->setFilter($this->getDefinition()->getFilter())
                ->setKey((string)$classFolder->getId())
                ->setPage($this->getPage())
                ->setSelected($abstractSelection->getSelected());
            $availableSelections->add($concreteSelection);
        }

        return $this->overlayUserSelections($availableSelections);
    }


    /**
     * @inheritdoc
     * @throws Mapper\Exception
     */
    public function toContext()
    {
        $page     = $this->getPage();
        $roleType = $this->getUser()->getRole()->getType();
        /** @var GenericCollection|Option\Model[] $availableOptions */
        $availableAbstractOptions    = $this->getDefinition()->getAvailableOptions($page, $roleType)[$page];
        $availableAbstractSelections = $this->getDefaultSelections($availableAbstractOptions);

        // Admin has configured whether both classes and groups should be available, and page has generated and inserted
        // the list of available classes and groups. We need to filter that list by the admin's config.
        $abstractClassSelection = $availableAbstractSelections[Definition::KEY_CLASSES];
        $abstractGroupSelection = $availableAbstractSelections[Definition::KEY_GROUPS];

        return [
            'isAvailable'     => $availableAbstractOptions->count() ? true : false,
            'component'       => $this,
            'classSelections' => $this->getConcreteSelections($abstractClassSelection, $this->classes),
            'groupSelections' => $this->getConcreteSelections($abstractGroupSelection, $this->groups),
        ];
    }


    /**
     * Converts a folder.id to the folder name
     * @param string $key
     * @return string
     * @throws \InvalidArgumentException If no folder found with the specified ID
     */
    public function getLabel($key)
    {
        if($this->classes->offsetExists($key)) {
            return $this->classes[$key]->getNameWithCodes();
        }
        if($this->groups->offsetExists($key)) {
            return $this->groups[$key]->getName();
        }
        throw new \InvalidArgumentException("Unknown FolderFilter key: $key");
    }

}
