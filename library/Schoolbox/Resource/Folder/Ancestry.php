<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Folder;

use OverflowException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Collection;

/**
 * Class Ancestry
 *
 * Get insights about folders who are parents of other folders
 *
 * @package Schoolbox\Resource\Folder
 */
class Ancestry
{

    /**
     * Maximum number of traversals before bugging out
     */
    private const RECURSION_LIMIT = 50;


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
    private $folderMapper;


    /**
     * @var self
     */
    private static $instance;


    /**
     * Constructor
     *
     * @param FolderMapper $folderMapper
     */
    private function __construct(FolderMapper $folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }


    /**
     * @param Mapper $folderMapper
     * @return static
     */
    public static function getInstance(FolderMapper $folderMapper): self
    {
        if (!self::$instance) {
            self::$instance = new static($folderMapper);
        }

        return self::$instance;
    }


    /**
     * Clear out all the objects you accumulated
     */
    public function reset()
    {
        $this->cache = [];
    }


    /**
     * Find ancestry, and return whether or not the given parent is in it.
     *
     * @param Model $folder
     * @param Model $parent
     * @return bool
     * @throws MapperException if one of the ancestors is deleted or missing somehow
     * @throws MapperException\Deleted
     * @throws OverflowException
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function hasParent(Folder $folder, Folder $parent)
    {
        $parentId    = $parent->getId();
        $ancestorIds = $this->findParentsCheaply($folder)->getColumnValues('id');

        return in_array($parentId, $ancestorIds);
    }

    /**
     * Find ancestry using findById() which also gives you hydrations
     * on joins and other objects
     *
     * @param Model $folder
     * @param Model $stopFolder
     * @return Collection
     * @throws MapperException if one of the ancestors is deleted or missing somehow
     * @throws MapperException\Deleted
     * @throws OverflowException if the folder is its own parent somehow
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function findParents(Folder $folder, Folder $stopFolder = null)
    {
        return $this->findParentAncestry($folder, false, $stopFolder);
    }

    /**
     * Find ancestry using findCheaplyById()
     *
     * @param Model $folder
     * @param Model $stopFolder
     * @return Collection
     * @throws MapperException if one of the ancestors is deleted or missing somehow
     * @throws MapperException\Deleted
     * @throws OverflowException if the folder is its own parent somehow
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function findParentsCheaply(Folder $folder, Folder $stopFolder = null)
    {
        return $this->findParentAncestry($folder, true, $stopFolder);
    }

    /**
     * Gets a collection of parents for the given category, starting from the
     * topmost parent first. Stores in a local cache for the Ancestry instance
     * to save on db calls where possible
     *
     * @param Model      $folder
     * @param bool       $cheaply    If true, use the findCheaplyById() for speed
     * @param Model|null $stopFolder If supplied, the parent list is truncated above this category.
     * @return Collection
     * @throws MapperException if one of the ancestors is deleted or missing somehow
     * @throws MapperException\Deleted
     * @throws OverflowException if the folder is its own parent somehow
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    private function findParentAncestry(Folder $folder, bool $cheaply, Folder $stopFolder = null): Collection
    {
        $stack = array($folder);
        $count = 0;
        $stop_id = null;

        if ($stopFolder instanceof Folder) {
            $stop_id = $stopFolder->getId();
        }

        /* Use $ancestry to keep track of which parents have been
         * encountered already; if we re-encounter something that
         * has been listed here, we end up with a node being in
         * its own ancestry, which is indicative of broken Folders
         */
        $ancestry = array();

        if ($folder->getId()) {
            $ancestry[$folder->getId()] = true;
        }

        // Get the parent of the first item in the stack, and add it to the top
        // of the stack.

        /* In case you somehow enter an infinite loop of nodes, place
         * a limit on the number of traversals/iterations/recurses that
         * can occur before arbitrarily ending
         */
        while ($count++ < self::RECURSION_LIMIT) {
            /* Cycle through the parent of the current node until
             * it is no longer a node at which point we can stop
             */
            $parent = $stack[0]->getParent();

            if (!($parent instanceof Folder)) {
                break;
            }

            $parent_id = $parent->getId();

            /* We can also stop if we read the $stopFolder
             */
            if (!$parent_id || (!empty($stop_id) && $stop_id == $parent_id)) {
                break;
            }

            // This category is its own parent somehow. That shouldn't happen.
            if (array_key_exists($parent_id, $ancestry)) {
                throw new OverflowException(
                    'Folder "' . $folder->getId() . '" is its own parent somehow'
                );
            }

            $ancestry[$parent_id] = true;

            try {
                array_unshift($stack, $this->findCachedNode($parent_id, $cheaply));
            } catch (MapperException $e) {
                // Some folder was deleted somewhere in the hierarchy.

                // Send this to NewRelic.
                if ($e instanceof MapperException\Deleted) {
                    $msg = 'Deleted Ancestor contains non-deleted Descendants';
                } else {
                    $msg = 'General Mapper Exception';
                }

                trigger_error("$msg: $e", E_USER_WARNING);

                // Rethrow to be managed by callers further up the stack
                throw $e;
            }
        }

        // Remove the current category from the parent list first.
        array_pop($stack);

        return new Collection($stack);
    }


    /**
     * Cache discovered nodes locally; when generating Ancestries
     * look here before slapping the db again
     *
     * @param int $id
     * @param bool $cheaply Use this to switch between findCheaplyById() and findById()
     * @return mixed
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws MapperException\NotFound
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    private function findCachedNode($id, bool $cheaply)
    {
        /* If it wasn't already cached, decide how complex the Folder
         * instance should be, and cache that for future reference
         */
        if (!($this->cache[$id] instanceof Folder)) {
            if ($cheaply) {
                $node = $this->folderMapper->findCheaplyById($id);
            } else {
                $node = $this->folderMapper->findById($id);
            }

            $this->cache[$id] = $node;
        }

        return $this->cache[$id];
    }
}
