<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Alaress\DataStructures;

use Closure;
use function array_combine;
use function array_key_exists;
use function array_map;
use function array_values;
use function call_user_func;
use function spl_object_hash;

/**
 * A fast data structure for storing tree-like elements.
 *
 * Designed to:
 * * support objects that do not have parent/children relationships themselves.
 * * ensure it is not possible to have conflicting relationships defined between tree/nodes
 *   (Eg, an alternate structure is to define nodes that have children and possibly parent relationships,
 *   and a tree which stores a reference to roots. If we update the tree but not nodes, or vice-versa, the
 *   structure can become corrupted.)
 *
 * * O(1) insert
 * * O(1) remove
 * * O(1) move
 * * O(1) contains
 * * O(1) size
 *
 * @todo type-declare object when migrate to PHP 7.2
 */
class ObjectTree
{

    // Where 1,2,3 are object hashes, and el1, el2, el3 are the object instances
    private $nodes           = [];    // [1 => el1, 2 => el2, 3 => el3]
    private $rootNodes       = [];    // [1 => el1]
    private $elementParents  = [];    // [2 => 1, 3 => 1, 1 => null]
    private $elementChildren = [];    // [1 => [2 => el2, 3 => el3]]

    /** @var callable|Closure */
    private $hashFunction;


    /**
     * The callable must return a unique value for all logically distinct items added to the tree.
     *
     * @param callable|null $hashFunction
     */
    public function __construct(callable $hashFunction = null)
    {
        $this->hashFunction = $hashFunction ?? static function ($element) {
                return spl_object_hash($element);
            };
    }


    /**
     * Returns a unique identifier for the specified object
     *
     * @param object $element
     * @return mixed
     */
    private function getHash($element)
    {
        return call_user_func($this->hashFunction, $element);
    }


    /**
     * Adds the element to the tree's nodes.
     *
     * Does not add the parent to the tree, but does store parent-to-child and child-to-parent relationships.
     *
     * @param object      $element
     * @param object|null $parent
     */
    public function addElement($element, $parent = null): void
    {
        $hash = $this->getHash($element);

        // Did this element already have a parent relationship registered?
        $oldParentHash = $this->elementParents[$hash] ?? null;
        if ($oldParentHash) {
            // Remove old parent-to-child relationship
            unset($this->elementChildren[$oldParentHash][$hash]);
        }

        // Is a new parent relationship declared?
        if ($parent) {
            $parentHash = $this->getHash($parent);
            // This node is not a root if it has a parent
            unset($this->rootNodes[$hash]);
            // Register the child-to-parent relationship
            $this->elementParents[$hash] = $parentHash;
            // Register the parent-to-child relationship
            $this->elementChildren[$parentHash][$hash] = $element;
        } else {
            // No parent, this node is a root, and has no parent
            $this->rootNodes[$hash]      = $element;
            $this->elementParents[$hash] = null;
        }

        $this->nodes[$hash] = $element;
    }


    /**
     * Returns whether this tree contains the element
     *
     * @param object $element
     * @return bool
     */
    public function containsElement($element): bool
    {
        return array_key_exists($this->getHash($element), $this->nodes);
    }


    /**
     * Returns the element specified by the hash, or null if none found
     *
     * @param $hash
     * @return object|null
     */
    public function getElementByHash($hash)
    {
        return $this->nodes[$hash] ?? null;
    }


    /**
     * Removes the element from the tree.
     *
     * Returns whether an item was removed.
     *
     * @param object $element
     * @return bool
     */
    public function removeElement($element): bool
    {
        if (!$this->containsElement($element)) {
            return false;
        }

        $hash       = $this->getHash($element);
        $parentHash = $this->elementParents[$hash] ?? null;

        if ($parentHash) {
            unset($this->elementChildren[$parentHash][$hash]);
        } else {
            unset($this->rootNodes[$hash]);
        }

        // Did this node have children registered? If so, they are now roots
        foreach ($this->elementChildren[$hash] ?? [] as $childHash => $childElement) {
            $this->rootNodes[$childHash] = $childElement;
        }

        unset($this->nodes[$hash], $this->elementParents[$hash]);

        return true;
    }


    /**
     * Update the specified node's parent.
     *
     * Returns whether the element is in tree.
     *
     * @param object $element
     * @param null   $parent
     * @return bool
     */
    public function moveElement($element, $parent = null): bool
    {
        if (!$this->containsElement($element)) {
            return false;
        }
        $this->addElement($element, $parent);
        return true;
    }


    /**
     * Returns the object's parent in the tree. Returns NULL if object not found, or is a root.
     *
     * @param object $element
     * @return object|null
     */
    public function getParent($element)
    {
        if (!$this->containsElement($element)) {
            return null;
        }
        return $this->nodes[$this->elementParents[$this->getHash($element)]] ?? null;
    }


    /**
     * Returns the elements children in the tree.
     *
     * Returns NULL if element not found in tree.
     *
     * @param object $element
     * @return array|null
     */
    public function getChildren($element): ?array
    {
        if (!$this->containsElement($element)) {
            return null;
        }
        return array_values($this->elementChildren[$this->getHash($element)] ?? []);
    }


    /**
     * Returns the tree's root elements as a flat array
     *
     * @return object[]
     */
    public function getRoots(): array
    {
        return array_combine(array_map($this->hashFunction, $this->rootNodes), $this->rootNodes);
    }


    /**
     * Returns the number of nodes in the tree
     *
     * @return int
     */
    public function size(): int
    {
        return count($this->nodes);
    }


    /**
     * Returns the set of elements as a flat array
     *
     * @return array
     */
    public function toFlatArray(): array
    {
        return array_combine(array_map($this->hashFunction, $this->nodes), $this->nodes);
    }


    /**
     * Returns the tree as an array. Does not mutate the tree.
     *
     * If root is specified and is not in tree, it will return empty array.
     * If root is specified and is in tree, then sub-tree from below node down will be returned
     * If not root specified, top-level will contain all nodes that do not have a parent registered
     *
     * @param object|null $root
     * @return ObjectNode[]
     */
    public function toNestedArray($root = null): array
    {
        if ($root) {
            $rootHash = $this->getHash($root);
            $roots    = $this->elementChildren[$rootHash] ?? [];
        } else {
            $roots = $this->rootNodes;
        }

        /** @var ObjectNode[] $tree */
        $tree = [];
        foreach ($roots as $hash => $element) {
            $tree[$hash] = new ObjectNode($element, $this->toNestedArray($element));
        }

        return $tree;
    }
}
