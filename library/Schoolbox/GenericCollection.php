<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox;

use TypeError;
use function class_exists;
use DomainException;
use function is_a;
use ReflectionClass;
use ReflectionException;
use UnexpectedValueException;

/**
 * Class GenericCollection
 *
 * Member items must be of declared type (or one of its subtypes)
 * Type is declared on construction or insertion of first element.
 * Type may be class or interface - not scalar or array
 *
 * @package Schoolbox
 */
class GenericCollection extends Collection
{
    /** @var string|null $elementType What type the elements in this Collection are allowed to be. Class/interface name */
    private $elementType = null;

    /**
     * Constructor
     *
     * @param string|object $elementType
     * @param array $stack
     */
    public function __construct($elementType, array $stack = [])
    {
        // If type is null, then set the type when first item is inserted

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
     * {@inheritDoc}
     *
     * @return static
     */
    public function emptyClone()
    {
        return new static($this->getElementClass());
    }

    /**
     * {@inheritDoc}
     *
     * @param array $stack
     * @return self
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
     * {@inheritDoc}
     *
     * @param $element
     * @return static
     */
    public function add($element)
    {
        $this->checkType($element);
        parent::add($element);
        return $this;
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     *
     * @param mixed $offset
     * @param mixed $value
     * @return static
     */
    public function offsetSet($offset, $value)
    {
        $this->checkType($value);
        parent::offsetSet($offset, $value);
        return $this;
    }


    /**
     * {@inheritDoc}
     *
     * @param int    $index
     * @param object $value
     * @return static
     * @throws UnexpectedValueException If $value is of an invalid type
     */
    public function setOffset($index, $value)
    {
        $this->checkType($value);
        parent::offsetSet($index, $value);
        return $this;
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
     * Returns whether this Generic Collection can only contain elements of the specified type.
     *
     * - A collection of *animals* is NOT a collection of *dogs*.
     * - A collection of *dogs* IS a collection of *animals*.
     *
     * @param string $otherElementType
     * @return bool
     * @throws ReflectionException
     */
    public function isCollectionOf(string $otherElementType): bool
    {
        // Are we dealing with classes or interfaces?
        $thisIsClass  = class_exists($this->elementType);
        $otherIsClass = class_exists($otherElementType);

        // Both classes?
        if($thisIsClass && $otherIsClass){
            return is_a($this->elementType, $otherElementType, true);
        }

        $thisReflection  = new ReflectionClass($this->elementType);

        // Both interfaces?
        if(!$thisIsClass && !$otherIsClass){
            return $thisReflection->implementsInterface($otherElementType);
        }

        // This is class (hence other is interface)
        if($thisIsClass){
            return $thisReflection->implementsInterface($otherElementType);
        }

        // (this is interface, other is class)
        return false;
    }

    /**
     * Asserts that this collection is a collection of the specified type.
     *
     * If $exact match, then element types must be exact matches, otherwise
     * applies OO principles to respect interface and class hierarchies.
     *
     * @param string $elementType
     * @param bool   $exact
     * @throws TypeError
     */
    public function assertCollectionOf(string $elementType, bool $exact = true): void
    {
        if ($exact && $this->elementType !== $elementType) {
            throw new TypeError(
                "These elements '{$this->elementType}' are not exact match of '$elementType'"
            );
        }
        try {
            if (!$this->isCollectionOf($elementType)) {
                throw new TypeError(
                    "These elements '{$this->elementType}' are not instance of '$elementType'"
                );
            }
        } catch (ReflectionException $e) {
            throw new TypeError(
                "These elements '{$this->elementType}' are not instance of '$elementType'",
                0,
                $e
            );
        }
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
     * @return static
     * @throws DomainException If collection has wrong member-item type
     */
    public function replaceWith(Collection $collection)
    {
        // Short-circuit via generics if we can
        if ($collection instanceof static && $this->assertSameItemClass($collection)) {
            parent::setStack($collection->getStack());
        } else {
            // Test every item individually
            parent::replaceWith($collection);
        }

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
        $thisItemClass  = $this->getElementClass();
        $otherItemClass = $newCollection->getElementClass();

        if ($thisItemClass != $otherItemClass) {
            throw new DomainException("Collection must be of type '$thisItemClass'. Found: '$otherItemClass'");
        }
    }
}
