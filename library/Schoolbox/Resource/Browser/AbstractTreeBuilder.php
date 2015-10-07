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
use Schoolbox\Resource;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\IFolder;


abstract class AbstractTreeBuilder
{
    /** @var Folder\ILocator */
    protected $folderLocator;

    /** @var string */
    protected $rootNodeLabel;


    /**
     * @param Folder\ILocator $locator
     */
    public function __construct(Folder\ILocator $locator)
    {
        $this->folderLocator = $locator;
        $this->rootNodeLabel = 'Root';
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
        $i            = 0;
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
                $this->buildChildNodes($parentCategoryId)
            );
        }

        // Open $categoryId's node and its parents
        foreach ($partialTree as &$leaf) {
            $leaf['state']['opened']   = in_array($leaf['id'], $parentList) || $leaf['id'] == 0;
            $leaf['state']['selected'] = ($leaf['id'] == $categoryId);
        }
        unset($leaf);

        return $partialTree;
    }


    /**
     * Builds a list of child nodes for $categoryId
     *
     * @param  mixed $folderId
     *
     * @return array
     */
    public function buildChildNodes($folderId)
    {
        $output = [];

        // Did I ask for the root node? get it
        if ($folderId === '#') {
            $output[] = $this->generateNodeArray(
                $this->folderLocator->findById(0),
                'root',
                $this->getRootNodeLabel(),
                [ 'data-permission' => $this->getNodePermission($folderId) ]
            );

        } else {
            // Child nodes
            foreach ($this->getNodeFolders($folderId) as $folder) {
                try {
                    $output[] = $this->buildNode($folder);
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
     * @throws Resource\Exception
     * @return array
     */
    protected abstract function buildNode(IFolder $folder);


    /**
     * @param IFolder $folder
     * @param string  $type
     * @param string  $name
     * @param array   $liAttributes
     * @param array   $aAttributes
     * @return array
     */
    protected function generateNodeArray(IFolder $folder, $type, $name = null, array $liAttributes = null, array $aAttributes = null)
    {
        $array = array(
            'id'       => $folder->getId(),
            'parent'   => $folder->getParent() instanceof IFolder ? $folder->getParent()->getId() : '0',
            'text'     => $name ?: $folder->getName(),
            'children' => $this->getNodeHasChildren($folder->getId()),
            'type'     => $type,
        );

        if ($liAttributes) {
            $array['li_attr'] = $liAttributes;
        }

        if ($aAttributes) {
            $array['a_attr'] = $aAttributes;
        }

        return $array;
    }


    /**
     * @param int $nodeId
     * @return bool
     */
    protected function getNodeHasChildren($nodeId)
    {
        return $this->getNodeFolders($nodeId)->count() > 0;
    }


    /**
     * @param int $nodeId
     * @return Collection|IFolder[]
     */
    protected abstract function getNodeFolders($nodeId);


    /**
     * @param int $nodeId
     * @return string
     */
    protected abstract function getNodePermission($nodeId);

} 