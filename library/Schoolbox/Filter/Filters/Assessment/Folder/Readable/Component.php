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


class Component extends BaseComponent
{
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
            $selected = $isInjected || ($selectMyGroups && $isGroup) || ($selectMyClasses && $isClass);
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
