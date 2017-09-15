<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder\TeacherInvolved;

use Schoolbox\Collection;
use Schoolbox\Filter;
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
        return new KeyConverter($filter, $folderIds, $this->getAccess());
    }


    /**
     * @inheritdoc
     * @throws \InvalidArgumentException If $viewee is NULL
     */
    protected function buildExtraComponentArgs(
        User $user,
        Filter\Page\Model $pageFilter,
        Filter\IDefinition $definition,
        Collection $overrides,
        User $viewee = null
    ) {

        if(null === $viewee){
            throw new \InvalidArgumentException('Viewee cannot be null');
        }

        // Should this role type see classes for this page?
        $options = $pageFilter->getOptions();

        if($pageFilter->getUserConfigurable()){
            $getClasses = $options[Definition::KEY_CLASSES]->getAvailable();
            $getGroups  = $options[Definition::KEY_GROUPS]->getAvailable();
        } else {
            $roleType   = $viewee->getRole()->getType();
            $getClasses = $options[Definition::KEY_CLASSES]->getDefaults()[$roleType]->getSelected();
            $getGroups  = $options[Definition::KEY_GROUPS]->getDefaults()[$roleType]->getSelected();

            // Are all available options unchecked ?
            if(!$getClasses && !$getGroups){
                // For consistency, this must logically equate to the filter not reducing the full set.
                $getClasses = $getGroups = true;
            }
        }

        // Note: '4' is a hack to mean 'INVALID', to force no group folders to be matched.
        $groupAccessLevel = $getGroups ? UserAccess::ACCESS_WRITE : UserAccess::ACCESS_FULL + 1;

        $folders = $this->getFolderMapper()->findForUser($viewee, $getClasses, $groupAccessLevel, false)->indexBy('id');

        // Did caller specify they wish to insert some custom folders in the list?
        // (Note that perm check was already performed by key-converter)
        $filterOverrides = $overrides[$definition->getFilter()->getId()];
        if ($filterOverrides && $filterOverrides->count()) {
            $folderIds    = $filterOverrides->getColumnValues('key');
            $extraFolders = $this->getFolderMapper()->findByColumn($this->getFolderMapper()->getIdentifierColumn(), $folderIds);

            foreach ($extraFolders as $extraFolder) {
                $folders[$extraFolder->getId()] = $extraFolder;
            }
        }

        return [
            'folders' => $folders->sortBy('name', 'ASC'),
        ];
    }
}
