<?php
/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Iterator;

use Schoolbox\External\Normalise\User;

/**
 * Class StackableTrait
 *
 * @package Schoolbox\Sync\Iterator
 */
trait StackableTrait
{
    /**
     * @var int
     */
    protected $rowNum = 0;

    /**
     * @var array|User\Base[]
     */
    protected $stack = [];

    /**
     * All classes which use this trait need to support this method
     * (@see \IteratorIterator)
     *
     * @return \Iterator
     */
    abstract public function getInnerIterator();

    /**
     * Creates a single record out of the stack.
     *
     * @param  array $preStack
     * @return User\Base
     */
    abstract protected function buildFromStack($preStack);

    /**
     * Gets the ID field out of this result set.
     *
     * @return string
     */
    abstract protected function getIdField();

    /**
     * Loads separate records from the iterator, and loads them into
     * the stack for this item.
     */
    protected function buildStack()
    {
        $id       = null;
        $preStack = [];
        while ($this->getInnerIterator()->valid() && $row = $this->getInnerIterator()->current()) {
            $thisId = $row[$this->getIdField()];
            if (!isset($id)) {
                $id = $thisId;
            }

            // Is this the next result?
            // Start building the result set using buildFromStack()
            if ($thisId != $id) {
                break;
            }
            $preStack[] = $row;
            $this->getInnerIterator()->next();
        }
        if (empty($preStack)) {
            // At the end of the result set
            $this->stack = [];
            return;
        }
        $this->stack[] = $this->buildFromStack($preStack);
    }

    /**
     * Checks if the iterator is valid
     *
     * @return bool
     */
    public function valid()
    {
        if (!empty($this->stack)) {
            return true;
        }
        $this->buildStack();
        return !empty($this->stack);
    }

    /**
     * Return the current element
     *
     * @return User\Base
     */
    public function current()
    {
        // Stack is managed by valid()
        return $this->stack[0];
    }

    /**
     * Move forward to next element
     */
    public function next()
    {
        array_shift($this->stack);
        $this->rowNum++;
    }

    /**
     * Get the key of the current element
     *
     * @return int
     */
    public function key()
    {
        return $this->rowNum;
    }
}
