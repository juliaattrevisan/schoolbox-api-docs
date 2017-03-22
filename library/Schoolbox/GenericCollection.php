<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox;

use DomainException;
use UnexpectedValueException;
use \ICollection;

/**
 * Member items must be of declared type.
 * Type is declared on construction or insertion of first element.
 * Type may be class or interface - not scalar or array
 */
class GenericCollection extends Collection
{
    /**
     * What type the elements in this Collection are allowed to be
     *
     * @var string
     */
    private $elementType = null;

    /**
     * If type is null, then set the type when first item is inserted
     *
     * @param string|object $elementType
     * @param array $stack
     *
     */
    public function __construct($elementType, array $stack = [])
    {
        if (is_string($elementType)) {
            $this->elementType = $elementType;
        } elseif (is_object($elementType)) {
            $this->elementType = get_class($elementType);
        } else {
            throw new UnexpectedValueException('Element Type must be a class or interface name, or object. Found: ' . gettype($elementType));
        }

        parent::__construct($stack);
    }

    /**
     * Returns the class/interface name of elements in this Collection
     *
     * @return string
     */
    public function getElementClass()
    {
        return $this->elementType;
    }

    /**
     * @return static
     */
    public function emptyClone()
    {
        return new static($this->getElementClass());
    }

    /**
     * Set stack
     *
     * @param $stack
     * @return static
     */
    protected function setStack($stack)
    {
        foreach ($stack as $item) {
            $this->checkType($item);
        }
        parent::setStack($stack);
        return $this;
    }

    /**
     * Ensures the $item is of the specified type
     *
     * @param mixed $item
     * @throws UnexpectedValueException if $item is not a valid element for this Collection
     */
    protected function checkType($item)
    {
        $type = gettype($item);

        if ($type !== 'object') {
            throw new UnexpectedValueException("Items cannot be {$type}s. Expected: {$this->elementType}");
        }

        if (!$item instanceof $this->elementType) {
            throw new UnexpectedValueException("Invalid type. Expected: {$this->elementType}, Actual: " . get_class($item));
        }
    }


    /**
     * Replace the internally held contents with the content of the given collection
     *
     * @param Collection $collection
     * @return $this
     */
    public function replaceWith(Collection $collection)
    {
        // short-circuit via generics if we can
        if ($collection instanceof static && $this->assertSameItemClass($collection)) {
            parent::setStack($collection->getStack());

        } else {
            // test every item individually
            parent::replaceWith($collection);
        }

        return $this;
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
     * @return static
     */
    public function prepend($element)
    {
        $this->checkType($element);
        parent::prepend($element);
        return $this;
    }

    /**
     * Sets key value in collection
     *
     * @param  mixed $offset
     * @param  mixed $value
     *
     * @return static
     */
    public function offsetSet($offset, $value)
    {
        $this->checkType($value);
        parent::offsetSet($offset, $value);
        return $this;
    }

    /**
     * Put an object in a particular place in the stack
     *
     * @param int $index
     * @param object $value
     * @return static
     */
    public function setOffset($index, $value)
    {
        $this->checkType($value);
        parent::offsetSet($index, $value);
        return $this;
    }

    /**
     * Ensure the proposed new Collection has the same member-item type as this Collection
     *
     * @param GenericCollection $newCollection
     * @throws DomainException if the new Collection has a different item-type to this Collection
     */
    protected function assertSameItemClass(GenericCollection $newCollection)
    {
        $thisItemClass = $this->getElementClass();
        $otherItemClass = $newCollection->getElementClass();
        if ($thisItemClass != $otherItemClass) {
            throw new DomainException("Collection must be of type '$thisItemClass'. Found: '$otherItemClass'");
        }
    }
}
