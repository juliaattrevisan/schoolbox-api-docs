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

namespace Schoolbox\Resource\Browser\Resources;

use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\IFolder as FolderInterface;
use Schoolbox\Resource\Browser\AbstractNode;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Exception;
use Schoolbox\Resource\File\Mapper as FileMapper;

/**
 * Class Node
 * Extends the default Browser\Node by fetching files in addition to folders
 *
 * @package SocialStream\Browser
 */
class Node extends AbstractNode
{

    /**
     * Library for determining permissions
     * @var Access
     */
    protected $access;


    /**
     * @param int         $nodeId
     * @param string|null $mimeFilter
     * @throws Exception
     * @throws \InvalidArgumentException
     */
    public function __construct($nodeId, $mimeFilter = null)
    {
        parent::__construct($nodeId, $mimeFilter);

        $this->setFolderLocator(FolderMapper::getInstance());
        $this->setFileLocator(FileMapper::getInstance());
        $this->setAccess(Access::getInstance());

        // Check we have permission to render node
        if ($nodeId > 0 && !$this->hasPermission($nodeId, UserAccess::ACCESS_READ)) {
            throw new Exception('Permission denied');
        }
    }


    /**
     * @param Access $access
     * @return AbstractNode
     */
    protected function setAccess($access)
    {
        $this->access = $access;
        return $this;
    }


    /**
     * @return Access
     */
    protected function getAccess()
    {
        return $this->access;
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
        return $this->getAccess()->getFolderAccess($_SESSION['user'], $nodeId) >= $minimumLevel;
    }


    /**
     * @param FolderInterface|Folder $folder
     * @return array
     */
    protected function folderToArray(FolderInterface $folder)
    {
        return array(
            'type'       => 'category',
            'categoryId' => $folder->getId(),
            'name'       => $folder->getTitle(),
            'folderType' => $folder->getNodeType(),
        );
    }
}
