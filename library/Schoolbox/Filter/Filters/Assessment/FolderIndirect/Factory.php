<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\FolderIndirect;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractFilterFactory;
use Schoolbox\Filter\IFilterDefinition;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\Filter\Selection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\Resource\Folder;


/**
 * A factory for building 'Folder' filter objects
 */
class Factory extends AbstractFilterFactory
{

    /**
     * @var Folder\Mapper
     */
    private $folderMapper;


    /**
     * @param Filter\Mapper    $filterMapper
     * @param Option\Mapper    $optionMapper
     * @param Defaults\Mapper  $defaultMapper
     * @param Selection\Mapper $selectionMapper
     * @param Folder\Mapper    $folderMapper
     */
    public function __construct(
        Filter\Mapper $filterMapper,
        Option\Mapper $optionMapper,
        Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        Folder\Mapper $folderMapper
    ) {
        parent::__construct($filterMapper, $optionMapper, $defaultMapper, $selectionMapper);
        $this->folderMapper = $folderMapper;
    }


    /**
     * @inheritdoc
     */
    protected function buildExtraComponentArgs(User $user, $page, IFilterDefinition $definition, User $viewee = null)
    {
        // Should this role type see classes for this page?
        /** @var Option\Model[][] $options */
        $options = $definition->getOptions($page);

        $getSubjects = $options[Definition::KEY_CLASSES][$page]->getAvailable();

        $groupAccessLevel = $options[Definition::KEY_GROUPS][$page]->getAvailable()
            ? UserAccess::ACCESS_WRITE
            : -1; // TODO - fix this dirty hack which is being used to prevent retrieval of group folders!

        $folders = $this->folderMapper->findForUser($viewee, $getSubjects, $groupAccessLevel, true);

        // Divide folders into Classes and Groups (not mutually exclusive!)

        // Does the folder have class codes ?
        $classes = $folders->filter(function (Folder\Model $folder) {
            return $folder->getCodes()->count();
        })->indexBy('id');

        // Does the folder have a group ?
        $groups = $folders->filter(function (Folder\Model $folder) {
            return $folder->getGroup();
        })->indexBy('id');

        return [
            'classes' => $classes,
            'groups'  => $groups,
        ];
    }


    /**
     * @inheritdoc
     */
    protected function buildKeyConverterArgs(Filter\Model $filter)
    {
        $folderIds = (new Collection($this->folderMapper->findAllIds(['deleted' => true])))->indexByCallback(function ($folderId) {
            return $folderId;
        });

        return array_merge(parent::buildKeyConverterArgs($filter), [
            'folderIds' => $folderIds,
        ]);
    }
}
