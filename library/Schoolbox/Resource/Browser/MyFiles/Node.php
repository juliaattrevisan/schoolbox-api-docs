<?php

/**
 * Node
 *
 * Render a list of categories and files which sit inside
 * a particular category.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\MyFiles;

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\My\File\Mapper as MyFileMapper;
use Schoolbox\Resource\My\Folder\Mapper as MyFolderMapper;
use Schoolbox\Resource\Browser\AbstractNode;
use Schoolbox\Resource\My\Folder\Model;
use Schoolbox\User;

/**
 * Class Node
 * Extends the default Browser\Node by fetching from MyFiles instead of Resources
 */
class Node extends AbstractNode
{

    /**
     * @param int  $nodeId
     * @param null $mimeFilter
     * @throws \Schoolbox\Resource\Exception
     */
    public function __construct($nodeId, $mimeFilter = null)
    {
        parent::__construct($nodeId, $mimeFilter);
        $this->setFolderLocator(new MyFolderMapper());
        $this->setFileLocator(new MyFileMapper());
    }

    /**
     * Get a list of breadcrumbs for this node
     * Return empty array, if the current node is not user's
     *
     * @return array
     */
    protected function getBreadcrumbs()
    {
        if (!$this->hasPermission($this->nodeId, UserAccess::ACCESS_READ)) {
            return [];
        }
        return parent::getBreadcrumbs();
    }

    /**
     * Returns whether or not permission can be granted for the given node
     *
     * @param int $nodeId
     * @param int $minimumLevel
     * @return bool
     */
    protected function hasPermission($nodeId, $minimumLevel)
    {
        $myFolder = $this->getFolderLocator()->findById($nodeId);
        return (
            $nodeId === 0
            || (
                $myFolder instanceof Model
                && $_SESSION['user'] instanceof User
                && $myFolder->getOwner()->getId() === $_SESSION['user']->getId()
            )
        );
    }
}
