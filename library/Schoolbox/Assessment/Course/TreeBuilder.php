<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Schoolbox\Resource\Browser\NodeInterface;
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

        // Don't build tree below courses or units
        $parent = $folder->getParent();
        $i      = 0;
        while ($parent && (int)$parent->getId() !== IFolder::ROOT_ID && $parent->getId() !== IFolder::ROOT_PARENT_ID && $i++ < 1000) {
            $folderType = $parent->getNodeType();
            if ($folderType === NodeInterface::TYPE_COURSE || $folderType === NodeInterface::TYPE_UNIT) {
                return false;
            }
            $parent = $parent->getParent();
        }
        return true;
    }


    /**
     * @inheritdoc
     */
    protected function getNodeHasChildren($nodeId, array $parentList = []): bool
    {
        // Are we checking the current course location node?
        if ((int)$nodeId === $this->currentParentId) {
            // We are only interested in children of current location if current location is valid
            return $this->isValidCourseLocation($nodeId);
        }

        $isAncestor = \in_array($nodeId, $parentList, false);
        if (!$isAncestor && NodeInterface::TYPE_COURSE === $this->folderLocator->findById($nodeId)->getNodeType()) {
            // If node is a course and not an ancestor, we are not interested in its children
            return false;
        }
        return parent::getNodeHasChildren($nodeId, $parentList);
    }


    /**
     * @inheritdoc
     */
    protected function getDataType(IFolder $folder, string $type): string
    {
        // Not selectable if a course or unit (but do permit current invalid parent to be retained)
        if (
            $folder->getId() !== $this->currentParentId &&
            \in_array($folder->getNodeType(), [NodeInterface::TYPE_COURSE, NodeInterface::TYPE_UNIT], true)
        ) {
            return NodeInterface::TYPE_NO_ACCESS;
        }
        return $type;
    }


    /**
     * @inheritdoc
     */
    protected function buildLiAttributes(IFolder $folder, string $permission, string $dataType): array
    {
        // Suppress leak of parent node title
        $title = '';

        if ($this->currentParentId === (int)$folder->getId()) {
            $title = 'Current course location';
        } elseif (NodeInterface::TYPE_NO_ACCESS === $dataType) {
            $title = 'Courses cannot be placed into other courses';
        }

        return parent::buildLiAttributes($folder, $permission, $dataType) + ['title' => $title];
    }


    /**
     * Returns whether a node is a valid course location
     *
     * @param int $nodeId
     * @return bool
     */
    public function isValidCourseLocation(int $nodeId): bool
    {
        $folder  = $this->folderLocator->findById($nodeId);
        $i       = 0;
        while ($folder && $folder->getId() && $i++ < 1000) {
            if (\in_array($folder->getNodeType(), [NodeInterface::TYPE_COURSE, NodeInterface::TYPE_UNIT], true)) {
                return false;
            }
            $folder = $this->folderLocator->findById($folder->getParent()->getId());
        }
        return true;
    }
}
