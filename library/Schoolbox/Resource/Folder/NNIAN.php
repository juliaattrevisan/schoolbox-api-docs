<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Folder;

use InvalidArgumentException;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use UnexpectedValueException;

/**
 * Responsible for resolving a folder to it's "Nearest Non Inheriting Ancestral Node"
 */
class NNIAN
{
    /** @var self */
    private static $instance;

    /** @var Mapper */
    private $folderMapper;

    /**
     * Array of cached NNIAN nodes
     *
     * To ensure database changes in long-running processes (eg Daemons) are respected,
     * this cache respects $this->isCacheEnabled()
     *
     * Maps folder.id to its NNIAN folder
     *
     * @var Model[]
     */
    private $cacheNnian = [];

    /**
     * @param Mapper $folderMapper
     * @return static
     */
    public static function getInstance(Mapper $folderMapper): self
    {
        if (!self::$instance) {
            self::$instance = new static($folderMapper);
        }
        return self::$instance;
    }


    /**
     * @param Mapper $folderMapper
     */
    private function __construct(Mapper $folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }


    /**
     * Finds the nearest non-inheriting ancestral node (NNIAN)
     *
     * If ancestral nodes are found in this mapper's localBodyCache, it will use those, otherwise
     * it will fall back to loading cheaply from database.
     *
     * Joins hydrated:
     * * none (the only guarantee is the the NNIAN will have its ID set)
     *
     * @param Model $folder
     * @return Model
     * @throws DefinitionException
     * @throws Deleted
     * @throws Exception
     * @throws Exception\InsufficientHydrationException
     * @throws Exception\NotFound
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function findNearestNonInheritingAncestralNode(Model $folder): Model
    {
        if ($folder->isRootNode()) {
            throw new InvalidArgumentException("Cannot find a NNIAN for Folder pseudo root");
        }

        // Check cache first (if enabled)
        if ($this->folderMapper->isCacheEnabled() && isset($this->cacheNnian[$folder->getId()])) {
            return $this->cacheNnian[$folder->getId()];
        }

        $interimNodes = $this->getNnianAncestors($folder);
        $nnian        = $interimNodes->last();

        // Ensure all interim nodes have their NNIAN cached
        foreach ($interimNodes as $node) {
            $this->cacheNnian[$node->getId()] = $nnian;
        }

        return $nnian;
    }


    /**
     * Returns the specified folder's nnian ancestry.
     *
     * This includes all folders from the specified folder to the NNIAN, inclusive.
     * The first item will be the passed in folder, the last item will be the NNIAN folder.
     *
     * @param Model $folder
     * @return GenericCollection<Model>|Model[] Indexed by folder.id
     * @throws DefinitionException
     * @throws Deleted
     * @throws Exception
     * @throws Exception\InsufficientHydrationException
     * @throws Exception\NotFound
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function getNnianAncestors(Model $folder): GenericCollection
    {
        if ($folder->isRootNode()) {
            throw new InvalidArgumentException("Cannot find a NNIAN ancestors for Folder pseudo root");
        }

        // Were we passed a stub? If so, hydrate it so we can use its parent.
        if ($folder->getParent() === null) {
            $folder = $this->folderMapper->findCheaplyById($folder->getId());
        }

        // Folders from $folder to NNIAN, inclusive
        $interimNodes = $this->folderMapper->createCollection([$folder->getId() => $folder]);
        $node         = $folder;
        while ($node->getInherit()) {

            // Climb ancestry tree using cheapest mechanism available.
            if ($node->getParent()->getParent() && $node->getParent()->getParent()->getId() !== null) {
                // Has this folder's parent already been sufficiently hydrated?
                $node = $node->getParent();
            } else {
                // Else load it cheaply
                $node = $this->folderMapper->findCheaplyById($node->getParent()->getId());
            }

            // Make sure we aren't hitting the root.
            // (There is a business rule that all top-level nodes must not inherit permissions)
            if ($node->isRootNode()) {
                throw new MapperException("Folder has no NNIAN");
            }

            // Check we don't have a cyclic ancestry graph
            if ($node->getId() === $folder->getId()) {
                throw new MapperException("Circular dependency detected");
            }

            $interimNodes->setOffset($node->getId(), $node);
        }

        return $interimNodes;
    }
}
