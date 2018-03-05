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
     * Constructor
     *
     * @param PortfolioWebDAV $dav
     */
    public function __construct(PortfolioWebDAV $dav = null)
    {
        parent::__construct($dav, 'webdavfile', 'Drives');

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
     * @param string $mime
     * @return Collection|Resource\AbstractFile[]
     */
    protected function getNodeFiles($nodeId, $mime = "*/*")
    {
        if ($this->fileLocator) {
            return $this->filterMime($this->fileLocator->findByFolderId($nodeId), $mime);
        }
        return new Collection();
    }


    /**
     * Return the given folder's colour
     *
     * @param IFolder $folder
     * @return string
     */
    protected function getFolderColour(IFolder $folder)
    {
        return $folder->getColour();
    }


    /**
     * Return the given folder's cover image
     *
     * @param IFolder $folder
     * @return string
     */
    protected function getFolderCoverImage(IFolder $folder)
    {
        return $folder->getCoverImage();
    }

    /**
     * Class specific filter for which folders appear in the tree
     *
     * @param IFolder $folder
     * @return bool
     */
    protected function filterFolder(IFolder $folder) {
        return true;
    }
}
