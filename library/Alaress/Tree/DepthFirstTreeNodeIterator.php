<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Tree;

use Schoolbox\Permission\Access\UserAccess;

/**
 * Class DeptjFirstTreeNodeIterator
 *
 * Iterates over a tree of nodes using depth-first traversal
 *
 *          0
 *       1     4
 *      2 3   5 6
 *
 * @package Alaress\Tree
 */

class DepthFirstTreeNodeIterator implements \Iterator
{
    const DIRECTION_LEFT  = 0;
    const DIRECTION_RIGHT = 1;

    /** @var int $position */
    protected $position;

    /** @var array $nodes */
    protected $nodes;


    /**
     * DepthFirstTreeNodeIterator constructor.
     * @param TreeNode $root
     * @param int $direction
     */
    public function __construct(TreeNode $root, $direction = self::DIRECTION_LEFT)
    {
        $this->nodes = array();
        $this->addNodeRecursive($root,$direction);
        $this->position = 0;
    }

    protected function addNodeRecursive(TreeNode $root, $direction)
    {
        $this->nodes[] = $root;
        $children = $root->getChildren();
        if(self::DIRECTION_RIGHT === $direction) {
            $children = array_reverse($children);
        }
        foreach($children as $child){
            $this->addNodeRecursive($child, $direction);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->position = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return $this->nodes[$this->position];
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        ++$this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return isset($this->nodes[$this->position]);
    }


}
