<?php
/**
 * Ancestry
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder;

use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Collection;

class Ancestry
{
    /**
     * Cache of Folder instances
     *
     * @var array
     */
    private $cache = [];

    /**
     * A FolderMapper
     *
     * @var FolderMapper
     */
    private $folderMapper = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setFolderMapper(FolderMapper::getInstance());
    }

    /**
     * Clear out all the objects you accumulated
     */
    public function reset()
    {
        $this->cache = [];
    }

    /**
     * Find ancestry using findById() which also gives you hydrations
     * on joins and other objects
     *
     * @param Model $folder
     * @param Model $stopFolder
     * @return Collection
     */
    public function findParents(Folder $folder, Folder $stopFolder)
    {
        return $this->findParentAncestry($folder, $stopFolder, false);
    }

    /**
     * Find ancestry using findCheaplyById()
     *
     * @param Model $folder
     * @param Model $stopFolder
     * @return Collection
     */
    public function findParentsCheaply(Folder $folder, Folder $stopFolder)
    {
        return $this->findParentAncestry($folder, $stopFolder, true);
    }

    /**
     * Gets a collection of parents for the given category, starting from the
     * topmost parent first. Stores in a local cache for the Ancestry instance
     * to save on db calls where possible
     *
     * @param  Folder $folder
     * @param  Folder $stopFolder If supplied, the parent list is truncated
     *                                 above this category.
     * @param bool $cheaply If true, use the findCheaplyById() for speed
     * @return Collection
     */
    private function findParentAncestry(Folder $folder, Folder $stopFolder = null, $cheaply = false)
    {
        /** @var Model[] $stack */
        $stack = array($folder);
        $count = 0;
        $stop_id = null;

        if ($stopFolder instanceof Folder) {
            $stop_id = $stopFolder->getId();
        }

        // Keep a record of ancestry
        $ancestry = array();

        if ($folder->getId()) {
            $ancestry[$folder->getId()] = true;
        }

        // Get the parent of the first item in the stack, and add it to the top
        // of the stack.
        while ($count++ < 50) {
            $parent = $stack[0]->getParent();
            if (!($parent instanceof Folder)) {
                break;
            }
            $parent_id = $parent->getId();
            if (!$parent_id || (!empty($stop_id) && $stop_id == $parent_id)) {
                break;
            }

            // This category is its own parent somehow. That shouldn't happen.
            if (array_key_exists($parent_id, $ancestry)) {
                throw new \OverflowException(
                    'Folder "'.$folder->getId().'" is its own parent somehow'
                );
            }
            $ancestry[$parent_id] = true;

            array_unshift($stack, $this->findCachedNode($parent_id, $cheaply));
        }

        // Remove the current category from the parent list first.
        array_pop($stack);

        return new Collection($stack);
    }

    /**
     * Cache discovered nodes locally; when generating Ancestries
     * look here before slapping the db again
     *
     * @param $id
     * @param $cheaply bool Use this to switch between findCheaplyById() and findById()
     * @return mixed
     */
    private function findCachedNode($id, $cheaply = false)
    {
        /* If it wasn't already cached, decide how complex the Folder
         * instance should be, and cache that for future reference
         */
        if (!($this->cache[$id] instanceof Folder)) {
            if ($cheaply) {
                $node = $this->getFolderMapper()->findCheaplyById($id);
            }
            else {
                $node = $this->getFolderMapper()->findById($id);
            }

            $this->cache[$id] = $node;
        }

        return $this->cache[$id];
    }

    /* Getters/Setters
     */

    /**
     * Get folder mapper
     *
     * @return Mapper
     */
    private function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * Set folder mapper
     *
     * @param Mapper $folderMapper
     */
    private function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }
}