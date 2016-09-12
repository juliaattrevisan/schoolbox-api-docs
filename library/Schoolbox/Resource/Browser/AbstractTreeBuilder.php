<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser;

use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\File;
use Schoolbox\Resource\Folder\IFolder;


abstract class AbstractTreeBuilder
{
    /** @var Folder\ILocator */
    protected $folderLocator;

    /** @var File\ILocator */
    protected $fileLocator;

    /** @var string */
    protected $rootNodeLabel;

    /** @var string */
    protected $namespace;


    /**
     * @param Folder\ILocator $locator
     * @param string $namespace
     * @param string $rootNodeLabel
     */
    public function __construct(Folder\ILocator $locator, $namespace, $rootNodeLabel = 'Top Level')
    {
        $this->folderLocator = $locator;
        $this->rootNodeLabel = $rootNodeLabel;
        $this->namespace = $namespace;
    }


    /**
     * @param File\ILocator $locator
     */
    public function setFileLocator(File\ILocator $locator)
    {
        $this->fileLocator = $locator;
    }


    /**
     * Builds a partial tree.
     *
     * This method loads $categoryId's children, then its parent's direct
     * children, and repeats all the way up to the parent node.
     *
     * @param  mixed $categoryId
     *
     * @return array
     */
    public function buildPartialTree($categoryId)
    {

        // build a list of parentage
        $parentList[] = $parentId = $categoryId;
        $i = 0;
        while ($parentId > 0) {
            if ($i++ > 100) {
                break;
            }
            try {
                $parentList[] = $parentId = $this->folderLocator->findById($parentId)->getParent()->getId();
            } catch (Mapper\Exception $e) {
                break;
            }
        }

        // Build the root node
        $partialTree = $this->buildChildNodes('#');

        // Build the parent nodes
        foreach ($parentList as $parentCategoryId) {
            $partialTree = array_merge(
                $partialTree,
                $this->buildChildNodes($parentCategoryId, $parentList)
            );
        }

        // Open $categoryId's node and its parents
        foreach ($partialTree as &$leaf) {
            $leaf['state']['opened'] = in_array($leaf['id'], $parentList) || $leaf['id'] == 0;
            $leaf['state']['selected'] = ($leaf['id'] == $categoryId);
        }
        unset($leaf);

        return $partialTree;
    }


    /**
     * Builds a list of child nodes for $categoryId
     *
     * @param mixed $folderId
     * @param array $mustInclude
     * @param string $mime
     * @return array
     */
    public function buildChildNodes($folderId, $mustInclude = [], $mime = "*?*")
    {
        $output = [];

        // Did I ask for the root node? get it
        if ($folderId === '#') {
            $folder = $this->folderLocator->findById(0);
            $output[] = $this->generateNodeArray(
                $folder->getId(),
                $folder->getParent()->getId(),
                'root',
                $this->getRootNodeLabel(),
                [
                    'data-permission' => $this->getFolderPermission($folderId),
                    'data-type' => 'folder'
                ]
            );

        } else {
            // Child nodes
            foreach ($this->getNodeFolders($folderId) as $folder) {
                try {
                    $output[] = $this->buildFolderNode($folder, $mustInclude);
                } catch (Resource\Exception $e) {
                    continue;
                }
            }
            // include files (which only works if a file locator was given)
            foreach ($this->getNodeFiles($folderId, $mime) as $file) {
                try {
                    $output[] = $this->buildFileNode($file);
                } catch (Resource\Exception $e) {
                    continue;
                }
            }
        }

        return $output;
    }


    /**
     * @return string
     */
    protected function getRootNodeLabel()
    {
        return $this->rootNodeLabel;
    }


    /**
     * @param IFolder $folder
     * @param array $mustInclude
     * @throws Resource\Exception
     * @return array
     */
    protected function buildFolderNode(IFolder $folder, $mustInclude = [])
    {
        // Get the permission this user has on this folder.
        // No permission: don't show it at all!
        $permission = $this->getFolderPermission($folder->getId());

        // this seems so trivial, but it's so these can be overridden
        $type = $this->getFolderIcon($folder);
        $name = $this->getFolderName($folder);
        $href = $this->getFolderHref($folder);

        if (empty($permission)) {
            // ensure that the direct tree to the selected node is available by generating ghost nodes
            if (in_array($folder->getId(), $mustInclude)) {
                $type = 'noaccess';
                $href = '#';
                $name = '(' . $folder->getName() . ')';
            } else {
                throw new Resource\Exception("No permission for this node");
            }
        }

        // Output something.
        return $this->generateFolderArray($folder, $type, $name,
            [
                'data-permission' => $permission,
                'data-type' => $type
            ],
            [
                'href' => $href
            ]
        );
    }


    /**
     * @param Resource\AbstractFile $file
     * @throws Resource\Exception
     * @return array
     */
    protected function buildFileNode(Resource\AbstractFile $file)
    {
        $permission = $this->getFilePermission($file);
        if (empty($permission)) {
            throw new Resource\Exception("No permission for this node");
        }

        return $this->generateFileArray($file, $file->getTitle(),
            [
                'data-permission' => $permission,
                'data-type' => 'file'
            ],
            [
                'href' => $this->getFileHref($file),
            ]
        );
    }


    /**
     * @param IFolder $folder
     * @param string $type
     * @param string $name
     * @param array $liAttributes
     * @param array $aAttributes
     * @return array
     */
    protected function generateFolderArray(IFolder $folder, $type, $name = null, array $liAttributes = null, array $aAttributes = null)
    {
        return $this->generateNodeArray($folder->getId(), $folder->getParent()->getId(), $type, $name ?: $folder->getName(), $liAttributes, $aAttributes);
    }


    /**
     * @param Resource\AbstractFile $file
     * @param string $name
     * @param array $liAttributes
     * @param array $aAttributes
     * @return array
     */
    protected function generateFileArray(Resource\AbstractFile $file, $name = null, array $liAttributes = null, array $aAttributes = null)
    {
        return $this->generateNodeArray($file->getId(), $file->getFolder()->getId(), 'file', $name ?: $file->getTitle(), $liAttributes, $aAttributes);
    }


    /**
     * @param int $nodeId
     * @param int $parentId
     * @param string $type
     * @param string $name
     * @param array $liAttributes
     * @param array $aAttributes
     * @return array
     */
    protected function generateNodeArray($nodeId, $parentId, $type, $name, array $liAttributes = null, array $aAttributes = null)
    {
        $array = array(
            'id' => $nodeId,
            'parent' => $parentId ?: '0',
            'text' => $name,
            'children' => 0,
            'type' => $type,
        );

        if ($type == 'file' || $type == 'link') {
            $array['id'] = "{$this->namespace}-$type-$nodeId";
        } else {
            $array['children'] = $this->getNodeHasChildren($nodeId);
        }

        if ($liAttributes) {
            $array['li_attr'] = $liAttributes;
        }

        if ($aAttributes) {
            $array['a_attr'] = $aAttributes;
        }

        if (empty($liAttributes['data-id'])) {
            $array['li_attr']['data-id'] = $nodeId;
        }

        return $array;
    }


    /**
     * @param int $nodeId
     * @return bool
     */
    protected function getNodeHasChildren($nodeId)
    {
        $count = $this->getNodeFolders($nodeId)->count();

        if ($this->fileLocator) {
            $count += $this->getNodeFiles($nodeId)->count();
        }

        return $count > 0;
    }


    /**
     * Return the given folder's type. By default is simply 'folder'.
     *
     * @param IFolder $folder
     * @return string
     */
    public function getFolderIcon(IFolder $folder)
    {
        return 'folder';
    }


    /**
     * Return the given folder's name. By default is just its name...
     *
     * @param IFolder $folder
     * @return string
     */
    protected function getFolderName(IFolder $folder)
    {
        return $folder->getName();
    }


    /**
     * Gets a permission string out of a permission code returned from
     * $this->handler->getPermission().
     *
     * @param  $code
     *
     * @return string
     */
    protected function getPermissionString($code)
    {
        switch ($code) {
            case UserAccess::ACCESS_FULL:
                return 'full';
                break;
            case UserAccess::ACCESS_WRITE:
                return 'write';
                break;
            case UserAccess::ACCESS_READ:
                return 'read';
                break;
            default:
                break;
        }
        return '';
    }


    /**
     * @param int $nodeId
     * @return string
     */
    protected function getFolderPermission($nodeId)
    {
        return $this->getPermissionString(UserAccess::ACCESS_FULL);
    }


    /**
     * @param  Resource\AbstractFile $file
     * @return string
     */
    protected function getFilePermission($file)
    {
        return $this->getPermissionString(UserAccess::ACCESS_FULL);
    }

    /**
     * Filter a collection of files by a mime filter
     * @param Collection $files
     * @param string $mime MIME type filter (e.g. 'image/jpeg', 'image/*')
     * @return Collection
     */
    protected function filterMime($files, $mime)
    {
        // Check if the mime if a wildcard
        if ($mime != "*/*") {

            $filesFiltered = new Collection();

            // Split the mime filter on '/'

            $mimeFilterArray = explode("/", $mime);

            // Iterate all files in the collection

            /** @var \Schoolbox\Resource\File\Model $file */
            foreach ($files as $file) {
                $match = true;

                // Get the current files mime type string split into an array
                $currentMime = explode("/", $file->getMime());

                for ($i = 0; $i < 2; $i++) {
                    // If current filter is wildcard, dont need to test
                    if ($mimeFilterArray[$i] != "*") {
                        if (strtolower($currentMime[$i] != strtolower($mimeFilterArray[$i]))) {
                            $match = false;
                        }
                    }
                }

                // If both sides of the mime filter matched, we want to return this file
                if ($match) {
                    $filesFiltered->add($file);
                }
            }

            return $filesFiltered;
        }

        // Wildcard filter, nothing to do
        return $files;
    }

    /**
     * Return the given folder's href
     *
     * @param IFolder $folder
     * @return string
     */
    protected abstract function getFolderHref(IFolder $folder);


    /**
     * Return the given file's href
     *
     * @param Resource\AbstractFile $file
     * @return string
     */
    protected abstract function getFileHref(Resource\AbstractFile $file);


    /**
     * @param int $nodeId
     * @return Collection|IFolder[]
     */
    protected abstract function getNodeFolders($nodeId);


    /**
     * @param int $nodeId
     * @param string $mime
     * @return Collection|Resource\AbstractFile[]
     */
    protected abstract function getNodeFiles($nodeId, $mime);
}
