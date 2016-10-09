<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox;

/**
 * Member items must be of declared type.
 * Type is declared on construction or insertion of first element.
 * Type may be class or interface - not scalar or array
 */
class GenericCollection extends Collection
{
    /**
     * What type the items in this Collection are allowed to be
     *
     * @var string
     */
    private $itemType = null;

    /**
     * If type is null, then set the type when first item is inserted
     *
     * @param array $stack
     * @param string|object|null $itemType
     */
    public function __construct(array $stack = [], $itemType = null)
    {
        if (is_string($itemType)) {
            $this->itemType = $itemType;
        } elseif (is_object($itemType)) {
            $this->itemType = get_class($itemType);
        } elseif (!is_null($itemType)) {
            throw new \UnexpectedValueException('Element Type must be a class or interface name, or object. Found: ' . gettype($itemType));
        }

        parent::__construct($stack);
    }

    /**
     * Set stack
     *
     * @param $stack
     */
    protected function setStack($stack)
    {
        foreach ($stack as $item) {
            $this->checkType($item);
        }
        parent::setStack($stack);
    }

    /**
     * Ensures the $item is of the specified type
     *
     * @param $item
     */
    protected function checkType($item)
    {
        if (is_scalar($item)) {
            throw new \UnexpectedValueException("Items cannot be scalar. Expected: {$this->itemType}, Actual: " . gettype($item));
        }

        if (is_array($item)) {
            throw new \UnexpectedValueException("Items cannot be arrays. Expected: {$this->itemType}, Actual: " . gettype($item));
        }

        // First item and no type yet set on this Collection?
        if(is_null($this->itemType)){
            $this->itemType = get_class($item);
        } else if (!$item instanceof $this->itemType) {
            throw new \UnexpectedValueException("Invalid type. Expected: {$this->itemType}, Actual: " . get_class($item));
        }
    }

    /**
     * Add an element ot the end of the stack
     *
     * @param $element
     * @return $this
     */
    public function add($element)
    {
        $this->checkType($element);
        parent::add($element);
        return $this;
    }

    /**
     * Prepend an element to the start of the stack
     *
     * @param $element
     */
    public function prepend($element)
    {
        $this->checkType($element);
        parent::prepend($element);
    }

    /**
     * Sets key value in collection
     *
     * @param  mixed $offset
     * @param  mixed $value
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->checkType($value);
        parent::offsetSet($offset, $value);
    }

    /**
     * Put an object in a particular place in the stack
     *
     * @param int $index
     * @param object $value
     */
    public function setOffset($index, $value)
    {
        $this->checkType($value);
        parent::offsetSet($index, $value);
    }
}