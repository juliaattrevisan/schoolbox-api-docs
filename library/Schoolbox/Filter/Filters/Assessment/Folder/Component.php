<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder;

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
    protected $folders;


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
     * @param GenericCollection|Filter\Page\Option\Model[] $abstractOptions
     * @throws \InvalidArgumentException If option key unrecognised
     */
    protected function buildRoleSelections(GenericCollection $abstractOptions)
    {
        $availableSelections = $this->getFilterSelectionMapper()->createCollection();
        $roleType            = $this->getUser()->getRole()->getType();

        // Had admin configured Groups and/or Classes to be selected for current user's role type?
        $selectGroups  = $abstractOptions[Definition::KEY_GROUPS]->getDefaults()[$roleType]->getSelected();
        $selectClasses = $abstractOptions[Definition::KEY_CLASSES]->getDefaults()[$roleType]->getSelected();

        $urlOverrides = $this->getUrlOverrides();

        foreach ($this->folders as $folder) {
            $key        = (string)$folder->getId();
            /* The factory will have injected folders where the:
             * - folder is injected via URL overrides
             * - folder is a class, and admin has configured taught classes to be available
             * - user has group WRITE+ perm to the folder and admin configured groups to be available
             *
             * We may have cases where a teacher has WRITE+ access to a group (via userAccess) that does not
             * exist (in userGroups). In this case, the relevant folder will be injected, but isGroup() will be false.
             *
             * Therefore if a folder was not injected, and is not a class, assume it is/was a group (whether due to
             * userGroups or userAccess).
             * TODO remove this when we can guarantee this data inconsistency to not exist: SA-5448
             */
            $isClass    = $folder->isClass() || $folder->wasClass();
            $isInjected = $urlOverrides && $urlOverrides->offsetExists($key);
            $isGroup    = !$isClass && !$isInjected;

            /* Should folder be selected?
             * - if injected via URL override
             * - is group and roleDefault for group is true
             * - is/was class and roleDefault for class is true
             */
            $selected = $isInjected || ($selectGroups && $isGroup) || ($selectClasses && $isClass);
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
