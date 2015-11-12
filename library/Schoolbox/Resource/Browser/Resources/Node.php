<?php
/**
 * Node
 *
 * Render a list of categories and files which sit inside
 * a particular category.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser\Resources;


use Schoolbox\Resource\Folder\IFolder as FolderInterface;
use Schoolbox\Mapper;
use Schoolbox\Resource\Browser\AbstractNode;
use Schoolbox\Resource\Folder\Model as Category;
use Schoolbox\Resource\Folder\Mapper as CategoryMapper;
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
     * @var \Group
     */
    protected $groupHandler;


    /**
     * @param int  $nodeId
     * @param null $mimeFilter
     * @throws \Schoolbox\Resource\Exception
     */
    public function __construct($nodeId, $mimeFilter = null)
    {
        parent::__construct($nodeId, $mimeFilter);

        $this->setFolderLocator(new CategoryMapper());
        $this->setFileLocator(new FileMapper());
        $this->setGroupHandler(new \Group());

        // Check we have permission to render node
        print_R($nodeId);
        if ($nodeId > 0 && !$this->hasPermission($nodeId, READ_ACCESS)) {
            throw new Exception('Permission denied');
        }
    }


    /**
     * @param \Group $groupHandler
     * @return AbstractNode
     */
    protected function setGroupHandler($groupHandler)
    {
        $this->groupHandler = $groupHandler;
        return $this;
    }


    /**
     * @return \Group
     */
    protected function getGroupHandler()
    {
        return $this->groupHandler;
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
        return $this->getGroupHandler()->getPermission($nodeId) >= $minimumLevel;
    }


    /**
     * @param FolderInterface|Category $folder
     * @return array
     */
    protected function folderToArray(FolderInterface $folder)
    {
        $name = $folder->getName();

        if ($codes = $folder->getCodeString()) {
            $name .= " ($codes)";
        }

        return array(
            'type'       => 'category',
            'categoryId' => $folder->getId(),
            'name'       => $name,
            'folderType' => $folder->getFolderType(),
        );
    }

}