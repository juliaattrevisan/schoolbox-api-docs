<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder\StudentInteractions;

use Schoolbox\Filter\IDefinition;
use Schoolbox\Filter\Option;
use Schoolbox\GenericCollection;
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
    protected function buildExtraComponentArgs(User $user, $page, IDefinition $definition, GenericCollection $overrides = null, User $viewee = null)
    {
        $folders = $this->getFolderMapper()->createCollection()
                        ->mergeUnique($this->getFolderMapper()->findFoldersWithFeedbackToStudent($viewee), 'id')
                        ->mergeUnique($this->getFolderMapper()->findFoldersWithResponses($viewee), 'id')
                        ->sortBy('name', 'ASC');


        // Should this role type see classes for this page?
        /** @var Option\Model[][] $options */
        $options = $definition->getOptions($page);

        $showClasses = $options[Definition::KEY_CLASSES][$page]->getAvailable();
        $showGroups  = $options[Definition::KEY_GROUPS][$page]->getAvailable();


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
