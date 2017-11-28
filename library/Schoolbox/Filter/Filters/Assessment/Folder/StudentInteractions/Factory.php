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
        $folders = $this->getFolderMapper()->createCollection();

        if (!$viewee) {
            throw new \InvalidArgumentException('$viewee cannot be null for StudentInteraction filter');
        }

        /** @noinspection PhpStatementHasEmptyBodyInspection */
        /** @noinspection MissingOrEmptyGroupStatementInspection */
        if($options[Definition::KEY_CLASSES]->getAvailable()){
            // TODO insert enrolled classes
        }
        /** @noinspection PhpStatementHasEmptyBodyInspection */
        /** @noinspection MissingOrEmptyGroupStatementInspection */
        if($options[Definition::KEY_GROUPS]->getAvailable()){
            // TODO insert enrolled groups
        }
        if($options[Definition::KEY_RESPONSE]->getAvailable()){
            $folders = $folders->mergeUnique($this->getFolderMapper()->findFoldersWithResponses($viewee), 'id');
        }
        if($options[Definition::KEY_FEEDBACK]->getAvailable()){
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
//        TODO resolve merge conflict remnant. Should we perm check client-supplied folder ids?
//        return new KeyConverter($filter, $folderIds, $this->getAccess());
        return new KeyConverter($filter, $folderIds);
    }
}
