<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder\StudentInteractions;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\Filter\Filters\Assessment\Folder as FolderFilter;


/**
 * A factory for building 'StudentFolder' filter objects
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
     * @param Filter\IDefinition                                               $definition
     * @param User                                                             $user
     * @param Filter\Page\Model                                                $pageFilter
     * @param null|GenericCollection|Filter\Selection\Model[]                  $selections Indexed selection.key
     * @param null|User                                                        $viewee
     * @param GenericCollection|GenericCollection[]|Filter\Selection\Model[][] $overrides  Indexed filter.id, selection.key
     * @return Filter\IComponent|FolderFilter\Component
     * @throws DefinitionException if join $name isn't on this mapper
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException If neither $selections or $overrides are supplied
     */
    public function buildComponent(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        ?GenericCollection $selections,
        ?User $viewee,
        GenericCollection $overrides
    ): Filter\IComponent {
        $options = $pageFilter->getOptions();

        if (!$viewee) {
            throw new \InvalidArgumentException('$viewee cannot be null for StudentInteraction filter');
        }

        $getClasses = $options[Definition::KEY_CLASSES]->getAvailable() && $viewee->getRole()->isStudent();
        $getGroups  = $options[Definition::KEY_GROUPS]->getAvailable();

        if ($getClasses || $getGroups) {
            // Note: '4' is a hack to mean 'INVALID', to force no group folders to be matched.
            $groupAccessLevel = $getGroups ? UserAccess::ACCESS_READ : UserAccess::ACCESS_FULL + 1;
            $folders          = $this->getFolderMapper()->findForUser($viewee, $getClasses, $groupAccessLevel, false)->indexBy('id');
        } else {
            $folders = $this->getFolderMapper()->createCollection();
        }

        if ($options[Definition::KEY_RESPONSE]->getAvailable()) {
            $folders = $folders->mergeUnique($this->getFolderMapper()->findFoldersWithResponses($viewee), 'id');
        }
        if ($options[Definition::KEY_FEEDBACK]->getAvailable()) {
            $folders = $folders->mergeUnique($this->getFolderMapper()->findFoldersWithFeedbackToStudent($viewee), 'id');
        }

        return new FolderFilter\Component(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $overrides[$pageFilter->getFilter()->getId()],
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
        return new KeyConverter($filter, $folderIds, $this->getAccess());
    }
}
