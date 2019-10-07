<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder\Readable;

use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\Folder\Component as BaseComponent;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder\Model;
use Schoolbox\User;


class Component extends BaseComponent
{
    /**
     * A list of the current user's classes that are eligible for selection.
     *
     * @var GenericCollection|Model[]
     */
    private $myClasses;


    /**
     * A list of the current user's groups that are eligible for selection.
     *
     * @var GenericCollection|Model[]
     */
    private $myGroups;


    /**
     * A list of classes that the current user is not involved in, and that are
     * eligible for selection.
     *
     * @var GenericCollection|Model[]
     */
    private $otherClasses;


    /**
     * A list of groups that the current user is not involved in, and that are
     * eligible for selection.
     *
     * @var GenericCollection|Model[]
     */
    private $otherGroups;


    /**
     * Constructor
     *
     * @param Filter\IDefinition      $definition
     * @param User                    $user
     * @param Filter\Page\Model       $pageFilter
     * @param GenericCollection|null  $userSelections
     * @param GenericCollection|null  $urlOverrides
     * @param Filter\Selection\Mapper $filterSelectionMapper
     * @param GenericCollection       $folders      Empty and unused in this context:
     *                                              split into `$myFolders` and `$otherFolders`
     * @param GenericCollection       $myClasses
     * @param GenericCollection       $myGroups
     * @param GenericCollection       $otherClasses
     * @param GenericCollection       $otherGroups
     */
    public function __construct(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        ?GenericCollection $userSelections,
        ?GenericCollection $urlOverrides,
        Filter\Selection\Mapper $filterSelectionMapper,
        GenericCollection $folders,
        GenericCollection $myClasses,
        GenericCollection $myGroups,
        GenericCollection $otherClasses,
        GenericCollection $otherGroups
    ) {
        parent::__construct(
            $definition,
            $user,
            $pageFilter,
            $userSelections,
            $urlOverrides,
            $filterSelectionMapper,
            $folders
        );
        $this->myClasses    = $myClasses;
        $this->myGroups     = $myGroups;
        $this->otherClasses = $otherClasses;
        $this->otherGroups  = $otherGroups;
    }


    /**
     * This component has no role selections.
     *
     * @inheritdoc
     * @param GenericCollection|Filter\Page\Option\Model[] $abstractOptions
     * @throws \InvalidArgumentException If option key unrecognised
     */
    protected function buildRoleSelections(GenericCollection $abstractOptions)
    {
        $availableSelections = $this->getFilterSelectionMapper()->createCollection();
        $roleType            = $this->getUser()->getRole()->getType();

        // Had admin configured Groups and/or Classes to be selected for current user's role type?
        $selectMyGroups     = $abstractOptions[Definition::KEY_MY_GROUPS]->getDefaults()[$roleType]->getSelected();
        $selectMyClasses    = $abstractOptions[Definition::KEY_MY_CLASSES]->getDefaults()[$roleType]->getSelected();
        $selectOtherGroups  = $abstractOptions[Definition::KEY_OTHER_GROUPS]->getDefaults()[$roleType]->getSelected();
        $selectOtherClasses = $abstractOptions[Definition::KEY_OTHER_CLASSES]->getDefaults()[$roleType]->getSelected();

        foreach ($this->myClasses as $folder) {
            $availableSelections[(string)$folder->getId()]
                = $this->convertFolderToSelection($folder, $selectMyClasses);
        }

        foreach ($this->myGroups as $folder) {
            $availableSelections[(string)$folder->getId()]
                = $this->convertFolderToSelection($folder, $selectMyGroups);
        }

        foreach ($this->otherClasses as $folder) {
            $availableSelections[(string)$folder->getId()]
                = $this->convertFolderToSelection($folder, $selectOtherClasses);
        }

        foreach ($this->otherGroups as $folder) {
            $availableSelections[(string)$folder->getId()]
                = $this->convertFolderToSelection($folder, $selectOtherGroups);
        }

        return $availableSelections;
    }


    /**
     * @inheritdoc
     */
    public function toContext()
    {
        return array_merge(
            parent::toContext(),
            [
                'myClasses'    => $this->myClasses,
                'myGroups'     => $this->myGroups,
                'otherClasses' => $this->otherClasses,
                'otherGroups'  => $this->otherGroups,
            ]
        );
    }


    /**
     * Converts a folder to a selection object.
     *
     * @param Model $folder
     * @param bool  $select Is this type of folder selected by default?
     * @return Filter\Selection\Model
     */
    protected function convertFolderToSelection(Model $folder, bool $select): Filter\Selection\Model
    {
        $key          = (string)$folder->getId();
        $urlOverrides = $this->getUrlOverrides();
        $isInjected   = $urlOverrides && $urlOverrides->offsetExists($key);
        return $this->createSelection($key, $isInjected || $select);
    }
}
