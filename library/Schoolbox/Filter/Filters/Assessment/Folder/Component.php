<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Resource\Folder;


class Component extends Filter\BaseComponent
{

    /**
     * @var GenericCollection|Folder\Model[]
     */
    private $folders;


    /**
     * @param Filter\IDefinition                              $definition
     * @param User                                            $user
     * @param Filter\Page\Model                               $pageFilter
     * @param null|GenericCollection|Filter\Selection\Model[] $userSelections
     * @param null|GenericCollection|Filter\Selection\Model[] $urlOverrides
     * @param Filter\Selection\Mapper                         $filterSelectionMapper
     * @param Collection|GenericCollection|Folder\Model[]     $folders
     * @throws \InvalidArgumentException If neither $userSelections or $urlOverrides are supplied
     */
    public function __construct(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        ?GenericCollection $userSelections,
        ?GenericCollection $urlOverrides,
        Filter\Selection\Mapper $filterSelectionMapper,
        GenericCollection $folders
    ) {
        parent::__construct($definition, $user, $pageFilter, $userSelections, $urlOverrides, $filterSelectionMapper);
        $this->folders = $folders;
    }


    /**
     * @inheritdoc
     * @param GenericCollection|Filter\Page\Option\Model[] $abstractOptions
     * @throws \InvalidArgumentException If option key unrecognised
     */
    protected function buildRoleSelections(GenericCollection $abstractOptions)
    {
        $availableSelections = $this->getFilterSelectionMapper()->createCollection();
        $roleType            = $this->getUser()->getRole()->getType();
        $isUserConfigurable  = $this->getPageFilter()->getUserConfigurable();

        // Had admin configured Groups and/or Classes to be available for current user's role type?
        // (if not user configurable, set to always available - filtering applied by roleDefault)
        $isGroupsAvailable  = $abstractOptions[Definition::KEY_GROUPS]->getAvailable();
        $isClassesAvailable = $abstractOptions[Definition::KEY_CLASSES]->getAvailable();

        // Had admin configured Groups and/or Classes to be selected for current user's role type?
        $selectGroups  = $abstractOptions[Definition::KEY_GROUPS]->getDefaults()[$roleType]->getSelected();
        $selectClasses = $abstractOptions[Definition::KEY_CLASSES]->getDefaults()[$roleType]->getSelected();

        foreach ($this->folders as $folder) {

            /* Should the folder be available?
             * - if not user configurable, set to always available - filtering applied by roleDefault
             * - is group and groups are available
             * - is/was class and classes are available
             */
            $available = !$isUserConfigurable || ($isGroupsAvailable && $folder->isGroup())
                || ($isClassesAvailable && ($folder->isClass() || $folder->wasClass()));
            if (!$available) {
                continue;
            }

            // Folder is available. Should it be selected?
            $selected = ($selectGroups && $folder->isGroup())
                || ($selectClasses && ($folder->isClass() || $folder->wasClass()));

            $key                       = (string)$folder->getId();
            $availableSelections[$key] = $this->createSelection($key, $selected);
        }
        return $availableSelections;
    }


    /**
     * @inheritdoc
     */
    public function toContext()
    {
        return array_merge(parent::toContext(), [
            'folders' => $this->folders,
        ]);
    }
}
