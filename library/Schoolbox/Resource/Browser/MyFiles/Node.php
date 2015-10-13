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

namespace Schoolbox\Resource\Browser\MyFiles;


use Schoolbox\Mapper;
use Schoolbox\Resource\My\File\Mapper as MyFileMapper;
use Schoolbox\Resource\My\Folder\Mapper as MyFolderMapper;
use Schoolbox\Resource\Browser\AbstractNode;

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
     * Returns whether or not permission can be granted for the given node
     *
     * @param int $nodeId
     * @param int $minimumLevel
     * @return bool
     */
    protected function hasPermission($nodeId, $minimumLevel)
    {
        return true;
    }

}