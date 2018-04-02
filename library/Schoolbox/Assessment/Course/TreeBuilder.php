<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Course;

use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\Resource\Browser\Resources;
use Schoolbox\User;


class TreeBuilder extends Resources\TreeBuilder
{

    /** @var int */
    private $currentParentId;


    /**
     * @param Folder\ILocator $locator
     * @param User            $user
     * @param int             $currentParentId
     * @param string          $rootLabel
     */
    public function __construct(Folder\ILocator $locator, User $user, int $currentParentId, string $rootLabel = 'Courses')
    {
        parent::__construct($locator, $user, $rootLabel);
        $this->currentParentId = $currentParentId;
    }


    /**
     * Class specific filter for which folders appear in the tree
     *
     * @param IFolder $folder
     * @param array   $parentList
     * @return bool
     */
    protected function filterFolder(IFolder $folder, array $parentList = []): bool
    {
        // Must include (because on branch from root to target node)?
        if (\in_array($folder->getId(), $parentList, false)) {
            return true;
        }
        // Don't build tree below courses
        if ($folder->getParent()->getFolderType() === 'course') {
            return false;
        }
        return true;
    }


    /**
     * @param int   $nodeId
     * @param array $parentList
     * @return bool
     */
    protected function getNodeHasChildren($nodeId, array $parentList = []): bool
    {
        // Is node on path between root and target node?
        if (\in_array($nodeId, $parentList, false)) {
            return parent::getNodeHasChildren($nodeId, $parentList);
        }

        // Otherwise prevent drilling into children of courses
        return $this->folderLocator->findById($nodeId)->getFolderType() !== 'course';
    }


    /**
     * @inheritdoc
     */
    protected function getDataType(IFolder $folder, string $type): string
    {
        // Not selectable if a course or unit (but do permit current invalid parent to be retained)
        if ($folder->getId() !== $this->currentParentId && \in_array($folder->getFolderType(), ['course', 'unit'], true)) {
            return 'noaccess';
        }
        return $type;
    }
}
