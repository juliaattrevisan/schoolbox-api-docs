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

namespace Schoolbox\Resource\Browser;


use InvalidArgumentException;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Resource\Folder\IFolder as FolderInterface;
use Schoolbox\Mapper;
use Schoolbox\Resource\File\ILocator as FileLocatorInterface;
use Schoolbox\Resource\Folder\ILocator as FolderLocatorInterface;

abstract class AbstractNode implements NodeInterface
{

    const MAX_DEPTH = 100;

    const ALL_FILES = null;
    const NO_FILES  = false;


    /**
     * A node id, usually a primary key
     *
     * @var int
     */
    protected $nodeId = 0;

    /**
     * Resultant node information
     *
     * @var array
     */
    protected $result = array();

    /**
     * Optional mime filter
     *
     * @var string
     */
    protected $mimeFilter = null;

    /**
     * @var FolderLocatorInterface
     */
    protected $folderLocator = null;

    /**
     * @var FileLocatorInterface
     */
    protected $fileLocator = null;


    /**
     * Constructor
     *
     * $nodeId is the primary key of the category whose contents
     * you wish to present
     *
     * @param int    $nodeId
     * @param string $mimeFilter
     * @throws \Schoolbox\Resource\Exception
     * @throws InvalidArgumentException
     */
    public function __construct($nodeId, $mimeFilter = null)
    {
        if (!is_numeric($nodeId)) {
            throw new InvalidArgumentException("Node ID must be numeric");
        }

        $this->setNodeId($nodeId);
        $this->setMimeFilter($mimeFilter);
    }


    /**
     * Render it out in a json format in case you need it
     */
    public function ajax()
    {
        header("Content-type: application/json");
        echo json_encode($this->getResult());
    }


    /**
     * Render it out so phplib can do it
     *
     * @return array
     */
    public function render()
    {
        $this->processNode();
        return $this->getResult();
    }


    /**
     * Combine the categories and files into a single list
     */
    protected function processNode()
    {
        $records = array_merge($this->getFolders(), $this->getFiles());

        $output = array(
            'breadcrumbs' => $this->getBreadcrumbs(),
            'records'     => $records,
        );

        $this->setResult($output);
    }


    /**
     * Get a list of breadcrumbs for this node
     *
     * @return array
     */
    protected function getBreadcrumbs()
    {
        $breadcrumbs = array();

        /* Special case for root node
         */
        if (!$this->getNodeId()) {
            return $breadcrumbs;
        }

        try {
            $folder = $this->getFolderLocator()->findById($this->getNodeId());

            $breadcrumbs[] = array(
                'categoryId' => $folder->getId(),
                'name'       => $folder->getName(),
            );

            for ($i = 0; ($folder->getParent()->getId() > 0) && ($i < self::MAX_DEPTH); $i++) {
                if ($this->hasPermission($folder->getParent()->getId(), READ_ACCESS)) {
                    $currentFolder = $this->getFolderLocator()->findById($folder->getParent()->getId());

                    $breadcrumbs[] = array(
                        'categoryId' => $currentFolder->getId(),
                        'name'       => $currentFolder->getName(),
                    );

                    $folder = $currentFolder;
                }
            }
        } catch(\Exception $e) {
            // we'll let this go, and just return whatever we got up to
        }

        return array_reverse($breadcrumbs);

    }


    /**
     * Get all the subcategories for a particular category
     *
     * @return array
     */
    protected function getFolders()
    {
        $output = array();

        $allFolders = $this->getFolderLocator()->findByParentId($this->getNodeId());

        foreach ($allFolders as $currentFolder) {
            if ($this->hasPermission($currentFolder->getId(), READ_ACCESS)) {
                $output[] = $this->folderToArray($currentFolder);
            }
        }

        return $output;
    }


    /**
     * @param FolderInterface $folder
     * @return array
     */
    protected function folderToArray(FolderInterface $folder)
    {
        return array(
            'type'       => 'category',
            'categoryId' => $folder->getId(),
            'name'       => $folder->getName(),
            'folderType' => $folder->getFolderType(),
        );
    }


    /**
     * Get all the files for a particular folder
     *
     * @return array
     */
    protected function getFiles()
    {
        $output = array();

        if ($this->mimeFilter === self::NO_FILES) {
            // no files if filter is explicitly false
            return $output;
        }

        $mimetypeMapper = new MimetypeMapper();

        //Double check perms, should not get here but just incase
        if (!$this->hasPermission($this->getNodeId(), READ_ACCESS)) {
            return $output;
        }


        try {
            $allFiles = $this->fileLocator->findByFolderId($this->getNodeId());
        } catch (\Exception $e) {
            return $output;
        }

        foreach ($allFiles as $currentFile) {

            /* Use a generic default icon for unknown filetypes that
              * could accumulate in the schoolbox
              */
            try {
                $mimetype = $currentFile->getMimetype();
            } catch (\Exception $e) {
                $mimetype = $mimetypeMapper->getUnknownMime();
            }

            /* If the mimetype table is empty, it'll spawn with a dunce object
             * SB-3911
             */
            if (!is_numeric($mimetype->getFileTypeID())) {
                $mimetype = $mimetypeMapper->getUnknownMime();
            }

            // now that we've done all this work, check it against the mimetype to see if we actually want it
            if (!$mimetype->matches($this->getMimeFilter())) {
                continue;
            }

            $record = array(
                'type'   => 'file',
                'fileId' => $currentFile->getId(),
                'name'   => $currentFile->getTitle(),
                'mime'   => $mimetype->getMime(),
                'icon'   => $mimetype->getIconFile(),
                'size'   => $currentFile->getSize(),
            );

            $output[] = $record;
        }

        return $output;
    }


    /* Getters/Setters
     */

    /**
     * Set node id
     *
     * @param int $nodeId
     */
    protected function setNodeId($nodeId)
    {
        $this->nodeId = $nodeId;
    }


    /**
     * Get node id
     *
     * @return int
     */
    protected function getNodeId()
    {
        return $this->nodeId;
    }


    /**
     * Set result
     *
     * @param array $result
     */
    protected function setResult($result)
    {
        $this->result = $result;
    }


    /**
     * Get result
     *
     * @return array
     */
    protected function getResult()
    {
        return $this->result;
    }


    /**
     * @param string $mimeFilter
     * @return AbstractNode
     */
    public function setMimeFilter($mimeFilter)
    {
        $this->mimeFilter = $mimeFilter;
        return $this;
    }


    /**
     * @return string
     */
    public function getMimeFilter()
    {
        return $this->mimeFilter;
    }


    /**
     * @param FolderLocatorInterface $locator
     * @return self
     */
    protected function setFolderLocator(FolderLocatorInterface $locator)
    {
        $this->folderLocator = $locator;
    }


    /**
     * @return FolderLocatorInterface
     */
    protected function getFolderLocator()
    {
        return $this->folderLocator;
    }


    /**
     * @param FileLocatorInterface $locator
     * @return self
     */
    protected function setFileLocator(FileLocatorInterface $locator)
    {
        $this->fileLocator = $locator;
        return $this;
    }


    /**
     * @return FileLocatorInterface
     */
    protected function getFileLocator()
    {
        return $this->fileLocator;
    }


    /**
     * Returns whether or not permission can be granted for the given node
     *
     * @param int $nodeId
     * @param int $minimumLevel
     * @return bool
     */
    protected abstract function hasPermission($nodeId, $minimumLevel);

}
