<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder\TeacherInvolved;

use Schoolbox\Filter\IDefinition;
use Schoolbox\Filter\Option;
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use Schoolbox\Filter\Filters\Assessment\Folder as FolderFilter;


/**
 * A factory for building 'Folder' filter objects
 */
class Factory extends FolderFilter\Factory
{

    /**
     * @inheritdoc
     */
    protected function buildExtraComponentArgs(User $user, $page, IDefinition $definition, GenericCollection $overrides = null, User $viewee = null)
    {
        // Should this role type see classes for this page?
        /** @var Option\Model[][] $options */
        $options = $definition->getOptions($page);

        $getSubjects = $options[Definition::KEY_CLASSES][$page]->getAvailable();

        $groupAccessLevel = $options[Definition::KEY_GROUPS][$page]->getAvailable()
            ? UserAccess::ACCESS_WRITE
            : -1; // TODO - fix this dirty hack which is being used to prevent retrieval of group folders!

        $folders = $this->getFolderMapper()->findForUser($viewee, $getSubjects, $groupAccessLevel, true)->indexBy('id');

        // Did caller specify they wish to insert some custom folders in the list?
        if ($overrides && $overrides->count()) {
            $folderIds    = $overrides->getColumnValues('key');
            $extraFolders = $this->getFolderMapper()->findByColumn($this->getFolderMapper()->getIdentifierColumn(), $folderIds);

            // Check permission
            $access = Access::getInstance();
            foreach ($extraFolders as $extraFolder) {
                if ($access->isInstructor($user, $extraFolder)) {
                    $folders[$extraFolder->getId()] = $extraFolder;
                }
            }
        }

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
}
