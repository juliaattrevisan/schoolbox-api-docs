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
    /** @var int $position */
    protected $position;

    /** @var array $array */
    protected $array;

    /**
     * BreadthFirstTreeNodeIterator constructor.
     * @param TreeNode $root
     */
    public function __construct(TreeNode $root)
    {
        $queue = new SplQueue();
        $queue->enqueue($root);

        try {
            while (($node = $queue->dequeue()) !== null) {
                $this->array[] = $node;

                foreach ($node->getChildren() as $childNode) {
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
        // Are we at final element?
        if(count($this->array) - 1 === $this->position)
        {
            // Try to read in next children
            $children = $this->array[$this->position]->getChildren();

            foreach ($children as $child) {
                $this->array[] = $child;
            }
        }
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