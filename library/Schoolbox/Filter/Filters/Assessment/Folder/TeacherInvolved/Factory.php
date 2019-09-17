<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder\TeacherInvolved;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\Folder\KeyConverter;
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\Filter\Filters\Assessment\Folder as FolderFilter;

/**
 * A factory for building Teacher 'Folder' filter objects
 */
class Factory extends FolderFilter\Factory
{
    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Definition|Filter\IDefinition
     */
    public function buildDefinition(Filter\Model $filter): Filter\IDefinition
    {
        return new Definition(
            $filter,
            $this->getOptionMapper(),
            $this->getDefaultsMapper()
        );
    }


    /**
     * The 'Teacher Folder' component has the following custom functionality:
     * * It inserts any folders which the current session user is involved with,
     * * It inserts any folders specified in GET args if user has READ+ perm to them
     * * Inserts any folders where GET-injected UnitImportSpecs reside
     *
     * @inheritDoc
     * @return Filter\IComponent|FolderFilter\Component
     * @throws InvalidArgumentException If $viewee is null
     */
    public function buildComponent(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        ?GenericCollection $selections,
        ?User $viewee,
        array $routeArgs,
        GenericCollection $overrides
    ): Filter\IComponent {
        if (null === $viewee) {
            throw new InvalidArgumentException('Viewee cannot be null');
        }

        // Should this role type see classes for this page?
        $options = $pageFilter->getOptions();

        if ($pageFilter->getUserConfigurable()) {
            $getClasses = $options[Definition::KEY_CLASSES]->getAvailable();
            $getGroups  = $options[Definition::KEY_GROUPS]->getAvailable();
        } else {
            $roleType   = $viewee->getRole()->getType();
            $getClasses = $options[Definition::KEY_CLASSES]->getDefaults()[$roleType]->getSelected();
            $getGroups  = $options[Definition::KEY_GROUPS]->getDefaults()[$roleType]->getSelected();

            // Are all available options unchecked ?
            if (!$getClasses && !$getGroups) {
                // For consistency, this must logically equate to the filter not reducing the full set.
                $getClasses = $getGroups = true;
            }
        }

        // Note: '4' is a hack to mean 'INVALID', to force no group folders to be matched.
        $groupAccessLevel = $getGroups ? UserAccess::ACCESS_WRITE : UserAccess::ACCESS_FULL + 1;

        $folders = $this->getFolderMapper()->findForUser($viewee, $getClasses, $groupAccessLevel, false)->indexBy('id');

        // Did caller specify they wish to insert some custom folders in the list?
        $folderOverrides = $overrides[$pageFilter->getFilter()->getId()];
        if ($folderOverrides && $folderOverrides->count()) {
            $folderIds    = $folderOverrides->getPropertyValues('key');
            $extraFolders = $this->getFolderMapper()->findByColumn($this->getFolderMapper()->getIdentifierColumn(), $folderIds);

            foreach ($extraFolders as $extraFolder) {
                // Ensure user is STAFF or has WRITE perm to the requested folder (this will also be enforced via key-converter)
                if ($user->getRole()->isStaff() || $this->getAccess()->getFolderAccess($user, $extraFolder->getId()) >= UserAccess::ACCESS_WRITE) {
                    $folders[$extraFolder->getId()] = $extraFolder;
                }
            }
        }

        return new FolderFilter\Component(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $folderOverrides,
            $this->getSelectionMapper(),
            $folders->sortBy('name', 'ASC', null, true)
        );
    }
}
