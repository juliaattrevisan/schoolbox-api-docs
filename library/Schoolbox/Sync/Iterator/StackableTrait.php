<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Iterator;

use Schoolbox\External\Normalise\User;

/**
 * Re-usable logic for an IteratorIterator where the outer iterator needs to return
 * items that may be composed of 1 or more contiguous items from the inner iterator.
 *
 * Eg,
 * From inner iterator:
 * * Item #1: id=1, email='me@example.com'
 * * Item #2: id=1, email='other@example.com'
 *
 * From outer iterator:
 * * Item #1: id=1, email='me@example.com; other@example.com'
 */
trait StackableTrait
{
    /**
     * @var int
     */
    protected $rowNum;

    /**
     * Composed from one or more contiguous items from the inner iterator
     *
     * @var User\Base|null
     */
    protected $consolidatedItem;

    /**
     * All classes which use this trait need to support this method
     * (@see \IteratorIterator)
     *
     * @return \Iterator
     */
    abstract public function getInnerIterator();

    /**
     * Creates a single record out of 1 or more rows of data
     *
     * @param  array $preStack
     * @return User\Base
     */
    abstract protected function buildFromStack(array $preStack);

    /**
     * Gets the ID field out of this result set.
     *
     * This is the field which determines which contiguous items from the inner iterator
     * are considered as belonging to the one logical entity.
     *
     * @return string
     */
    abstract protected function getIdField();

    /**
     * Retrieves 1 or more distinct records from the inner iterator,
     * and consolidates them into a single item.
     *
     * After this method returns, the inner iterator will already be pointing at next item.
     * We need to inspect the next item after the current item so we can determine we have
     * finished consolidating sub-items from the inner iterator.
     */
    protected function buildStack()
    {
        $id       = null;
        $preStack = [];

        while ($this->getInnerIterator()->valid() && $row = $this->getInnerIterator()->current()) {
            $thisId = $row[$this->getIdField()];

            // First sub-item for logical entity ?
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
            $this->consolidatedItem = null;
            return;
        }
        $this->consolidatedItem = $this->buildFromStack($preStack);
    }

    /**
     * Checks if the iterator's current position is valid
     *
     * @return bool
     */
    public function valid()
    {
        return $this->consolidatedItem instanceof User\Base;
    }

    /**
     * Return the current element
     *
     * @return User\Base|null
     */
    public function current()
    {
        return $this->consolidatedItem;
    }

    /**
     * Move forward to next element
     */
    public function next()
    {
        // Intentionally does not directly call $innerIterator->next() as each execution
        // of buildStack() indirectly generates 1 or more calls to $innerIterator->next()
        $this->rowNum++;
        $this->buildStack();
    }

    /**
     * Get the key of the current element
     *
     * @return int|null
     */
    public function key()
    {
        return $this->rowNum;
    }

    /**
     * Resets the pointer to 0 and loads first item ready for retrieval.
     */
    public function rewind()
    {
        $this->getInnerIterator()->rewind();

        $this->rowNum = 0;
        $this->buildStack();
    }
}
