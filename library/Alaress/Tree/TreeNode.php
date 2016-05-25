<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Tree;


/**
 * Class TreeNode
 * A generic tree node that can hold both scalar and object values
 *
 * @package Alaress\Tree
 */
class TreeNode
{
    /**
     * Must match associated item's id
     * @var int $id
     */
    protected $id;

    /**
     * Must match any associated parent node object's id
     * @var int $parent
     */
    protected $parent;

    /** @var TreeNode[]|array $children */
    protected $children;

    /**  @var mixed $item */
    protected $item;

    /**
     * TreeNode constructor.
     * @param mixed $item
     * @param int $id
     * @param TreeNode $parent
     * @param TreeNode[]|array $children
     */
    public function __construct($item, $id, $parent = NULL, $children = array() )
    {
        $this->id       = $id;
        $this->parent   = $parent;
        $this->children = $children;
        $this->item     = $item;
    }

    /**
     * @return mixed
     */
    public function getItem(){
        return $this->item;
    }

    /**
     * @return int
     */
    public function getId(){
        return $this->id;
    }

    /**
     * @return int|null
     */
    public function getParent(){
        return $this->parent;
    }


    /**
     * @return TreeNode[]|array
     */
    public function getChildren(){
        return $this->children;
    }

    /**
     * Does not currently ensure $child not already in $children
     * @param TreeNode $child
     */
    public function addChild(TreeNode $child){
        $this->children[] = $child;
    }

    /**
     * Returns an Iterator that performs a breath-first traversal
     * @return BreadthFirstTreeNodeIterator
     */
    public function itrBreadthFirst(){
        return new BreadthFirstTreeNodeIterator($this);
    }
}