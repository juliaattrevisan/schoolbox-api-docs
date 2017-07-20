<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder\StudentInteractions;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use Schoolbox\Filter\Filters\Assessment\Folder as FolderFilter;


/**
 * A factory for building 'StudentFolder' filter objects
 */
class Factory extends FolderFilter\Factory
{

    /**
     * @inheritdoc
     * @throws DefinitionException If join not defined
     */
    protected function buildExtraComponentArgs(
        User $user,
        Filter\Page\Model $pageFilter,
        Filter\IDefinition $definition,
        Collection $overrides,
        User $viewee = null
    ) {
        $folders = $this->getFolderMapper()->createCollection()
                        ->mergeUnique($this->getFolderMapper()->findFoldersWithFeedbackToStudent($viewee), 'id')
                        ->mergeUnique($this->getFolderMapper()->findFoldersWithResponses($viewee), 'id')
                        ->sortBy('name', 'ASC');


        // Should this role type see classes for this page?
        $options = $pageFilter->getOptions();

        $showClasses = $options[Definition::KEY_CLASSES]->getAvailable();
        $showGroups  = $options[Definition::KEY_GROUPS]->getAvailable();


        // Divide folders into Classes and Groups (not mutually exclusive!)

        // Does the folder have class codes ?
        $classes = $folders->filter(function (Folder\Model $folder) use ($showClasses) {
            return $showClasses && $folder->getCodes()->count();
        })->indexBy('id');

        // Does the folder have a group ?
        $groups = $folders->filter(function (Folder\Model $folder) use ($showGroups) {
            return $showGroups && $folder->getGroup();
        })->indexBy('id');

        return [
            'classes' => $classes,
            'groups'  => $groups,
        ];
    }
}
