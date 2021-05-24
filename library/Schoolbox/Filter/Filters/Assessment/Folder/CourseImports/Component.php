<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder\CourseImports;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Resource\Folder;

class Component extends Filter\BaseComponent
{

    /**
     * Indexed by folder.id
     *
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
     * @param Collection|GenericCollection|Folder\Model[]     $folders Indexed by folder.id
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
     */
    public function toContext()
    {
        return array_merge(parent::toContext(), [
            'folders' => $this->folders,
        ]);
    }

    /**
     * @inheritdoc
     * @param GenericCollection|Filter\Page\Option\Model[] $abstractOptions
     */
    protected function buildRoleSelections(GenericCollection $abstractOptions)
    {
        $availableSelections = $this->getFilterSelectionMapper()->createCollection();
        $roleType            = $this->getUser()->getRole()->getType();

        // Had admin configured Groups and/or Classes to be selected for current user's role type?
        $selectGroups  = $abstractOptions[Definition::KEY_GROUPS]->getDefaults()[$roleType]->getSelected();
        $selectClasses = $abstractOptions[Definition::KEY_CLASSES]->getDefaults()[$roleType]->getSelected();

        foreach ($this->folders as $folder) {
            $key                       = (string)$folder->getId();
            $isClass                   = $folder->isClass() || $folder->wasClass();
            $isGroup                   = $folder->isGroup();
            $selected                  = ($selectGroups && $isGroup) || ($selectClasses && $isClass);
            $availableSelections[$key] = $this->createSelection($key, $selected);
        }
        return $availableSelections;
    }
}
