<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser\WebDav;


use PortfolioWebDAV;
use Schoolbox\Collection;
use Schoolbox\Resource;
use Schoolbox\Resource\Browser\AbstractTreeBuilder;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\IFolder;

class TreeBuilder extends AbstractTreeBuilder
{

    /**
     */
    public function __construct()
    {
        $dav = PortfolioWebDAV::factory();

        parent::__construct($dav, 'webdavfile');
        $this->fileLocator = $dav;
    }


    /**
     * Return the given folder's href
     *
     * @param IFolder $folder
     * @return string
     */
    protected function getFolderHref(IFolder $folder)
    {
        return '#';
    }


    /**
     * Return the given file's href
     *
     * @param Resource\AbstractFile $file
     * @return string
     */
    protected function getFileHref(Resource\AbstractFile $file)
    {
        return '#';
    }


    /**
     * @param int $nodeId
     * @return Collection|IFolder[]
     */
    protected function getNodeFolders($nodeId)
    {
        return $this->folderLocator->findByParentId($nodeId);
    }


    /**
     * @param int $nodeId
     * @return Collection|Resource\AbstractFile[]
     */
    protected function getNodeFiles($nodeId)
    {
        if ($this->fileLocator) {
            return $this->fileLocator->findByFolderId($nodeId);
        }
        return new Collection();
    }


}