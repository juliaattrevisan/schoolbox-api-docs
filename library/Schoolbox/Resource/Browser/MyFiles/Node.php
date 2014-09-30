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


use Schoolbox\FileInterface;
use Schoolbox\Mapper;
use Schoolbox\MyFileMapper;
use Schoolbox\MyFolderMapper;
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
        $this->setFolderMapper(new MyFolderMapper());
        $this->setFileMapper(new MyFileMapper());
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


    /**
     * @param int $nodeId
     * @return mixed
     */
    protected function findFoldersForNode($nodeId)
    {
        return $this->getFolderMapper()->findByColumnList(array(
            'parent' => $this->getNodeId(),
            'owner'  => $_SESSION['id'] // TODO don't merge this
        ));
    }


    /**
     * @param int $nodeId
     * @return FileInterface
     */
    protected function findFilesForNode($nodeId)
    {
        return $this->getFileMapper()->findByColumnList(array(
            'parent' => $this->getNodeId(),
            'owner'  => $_SESSION['id'] // TODO don't merge this
        ));
    }

}