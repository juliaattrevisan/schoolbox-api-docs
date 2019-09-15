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
     * A list of folders that are eligible for selection, that the current user
     * is NOT directly involved in.
     *
     * @var GenericCollection|Model[]
     */
    private $otherFolders;

    /**
     * Constructor
     *
     * @param Filter\IDefinition      $definition
     * @param User                    $user
     * @param Filter\Page\Model       $pageFilter
     * @param GenericCollection|null  $userSelections
     * @param GenericCollection|null  $urlOverrides
     * @param Filter\Selection\Mapper $filterSelectionMapper
     * @param GenericCollection       $folders
     * @param GenericCollection       $otherFolders
     */
    public function __construct(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        ?GenericCollection $userSelections,
        ?GenericCollection $urlOverrides,
        Filter\Selection\Mapper $filterSelectionMapper,
        GenericCollection $folders,
        GenericCollection $otherFolders
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
        $this->otherFolders = $otherFolders;
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

        $urlOverrides = $this->getUrlOverrides();

        foreach ($this->folders as $folder) {
            // TODO Should this filter fetch folders that were classes?
            $key        = (string)$folder->getId();
            $isClass    = $folder->isClass();
            $isInjected = $urlOverrides && $urlOverrides->offsetExists($key);
            $isGroup    = !$isClass && !$isInjected;

            /* Should folder be selected?
             * - if injected via URL override
             * - is group and roleDefault for group is true
             * - is/was class and roleDefault for class is true
             */
            $selected = $isInjected
                || ($selectMyGroups && $isGroup)
                || ($selectMyClasses && $isClass);
            $availableSelections[$key] = $this->createSelection($key, $selected);
        }

        foreach ($this->otherFolders as $folder) {
            $key        = (string)$folder->getId();
            $isClass    = $folder->isClass();
            $isInjected = $urlOverrides && $urlOverrides->offsetExists($key);
            $isGroup    = !$isClass && !$isInjected;
            $selected   = $isInjected
                || ($selectOtherGroups && $isGroup)
                || ($selectOtherClasses && $isClass);
            $availableSelections[$key] = $this->createSelection($key, $selected);
        }

        return $availableSelections;
    }


    /**
     * @param GenericCollection $roleSelections
     * @return Filter\Selection\Model[]|GenericCollection
     */
    protected function buildUserSelections(GenericCollection $roleSelections)
    {
        $userSelections = $roleSelections->emptyClone();
        $urlOverrides   = $this->getUrlOverrides();
        if (is_iterable($urlOverrides)) {
            foreach ($urlOverrides as $urlOverride) {
                $key                  = $urlOverride->getKey();
                $userSelections[$key] = $this->createSelection($key, true);
            }
        }
        return $userSelections;
    }
}
