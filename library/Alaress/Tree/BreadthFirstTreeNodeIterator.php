<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Tree;

use Zend\Stdlib\SplQueue;


/**
 * Class BreadthFirstTreeNodeIterator
 * 
 * Iterates over a tree of nodes using breath-first traversal
 * 
 *          0
 *       1     2
 *      3 4   5 6  
 * 
 * @package Alaress\Tree
 */
class BreadthFirstTreeNodeIterator implements \Iterator
{
    const DIRECTION_LEFT  = 0;
    const DIRECTION_RIGHT = 1;

    /** @var int $position */
    protected $position;

    /** @var array $array */
    protected $array;

    /**
     * BreadthFirstTreeNodeIterator constructor.
     * @param TreeNode $root
     * @param int $direction
     */
    public function __construct(TreeNode $root, $direction = self::DIRECTION_LEFT)
    {
        $queue = new SplQueue();
        $queue->enqueue($root);

        try {
            while (($node = $queue->dequeue()) !== null) {
                $this->array[] = $node;
                $children = $node->getChildren();
                if(self::DIRECTION_RIGHT === $direction){
                    $children = array_reverse($children);
                }
                foreach ($children as $childNode) {
                    $queue->enqueue($childNode);
                }
            }
        } catch (\RuntimeException $e){
            // Expected to occur when end of queue reached.
        }
        $this->position = 0;
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
        return $this->array[$this->position];
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
        return isset($this->array[$this->position]);
    }
}