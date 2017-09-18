<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder\TeacherInvolved;

use Schoolbox\Collection;
use Schoolbox\Filter;
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
     * @param Filter\IDefinition     $definition
     * @param User                   $user
     * @param Filter\Page\Model      $pageFilter
     * @param GenericCollection      $selections
     * @param null|User              $viewee
     * @param null|GenericCollection $overrides
     * @return Filter\IComponent|FolderFilter\Component
     * @throws \InvalidArgumentException If $viewee is null
     */
    public function buildComponent(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        GenericCollection $selections,
        User $viewee = null,
        GenericCollection $overrides = null
    ): Filter\IComponent {
        if (null === $viewee) {
            throw new \InvalidArgumentException('Viewee cannot be null');
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
        // (Note that perm check was already performed by key-converter)
        if ($overrides && $overrides->count()) {
            $folderIds    = $overrides->getColumnValues('key');
            $extraFolders = $this->getFolderMapper()->findByColumn($this->getFolderMapper()->getIdentifierColumn(), $folderIds);

            foreach ($extraFolders as $extraFolder) {
                $folders[$extraFolder->getId()] = $extraFolder;
            }
        }

        return new FolderFilter\Component(
            $definition,
            $user,
            $pageFilter,
            $overrides ?: $selections,
            $this->getSelectionMapper(),
            $folders->sortBy('name', 'ASC')
        );
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        $folderIds = (new Collection($this->getFolderMapper()->findAllIds(['deleted' => true])))
            ->indexByCallback(function ($folderId) {
                return $folderId;
            });
//        TODO resolve merge conflict remnant. Should we perm check client-supplied folder ids?
//        return new KeyConverter($filter, $folderIds, $this->getAccess());
        return new KeyConverter($filter, $folderIds);
    }
}
