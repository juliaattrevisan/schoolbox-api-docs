<?php
/**
 * Base collection of model objects.
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

use Alaress\Json\Decorator\IDecorator;
use ArrayAccess;
use InvalidArgumentException;
use ArrayIterator;
use ICollection;
use JsonSerializable;

class Collection implements ICollection, \Alaress\Json\Serializable, ArrayAccess
{
    /** @var array */
    protected $stack = array();

    /** @var int */
    protected $position = 0;

    /**
     * Only set a stack parameter if it's an array
     *
     * @param array $stack
     */
    public function __construct($stack = array())
    {
        if (is_array($stack)) {
            $this->setStack($stack);
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
        $this->stack[] = $element;

        return $this;
    }

    /**
     * @return static
     */
    public function emptyClone()
    {
        return new static();
    }

    /**
     * Prepend an element to the start of the stack
     *
     * @param $element
     * @return $this
     */
    public function prepend($element)
    {
        array_unshift($this->stack, $element);
        return $this;
    }

    /**
     * Returns whole collection as an array.
     *
     * @return array
     */
    public function getAll()
    {
        return $this->getStack();
    }


    /**
     * Order(N) traversal to return whether the collection contains
     * the specified item using strict comparison.
     *
     * If the collection contains primitives, then they must be same value + type
     * If the collection contains objects, they must be the same object
     *
     * @param mixed $item
     * @return bool
     */
    public function contains($item)
    {
        foreach ($this->stack as $stackItem) {
            if ($stackItem === $item) {
                return true;
            }
        }
        return false;
    }

    /**
     * Sets key value in collection
     *
     * @param  mixed $offset
     * @param  mixed $value
     *
     * @return $this
     */
    public function offsetSet($offset, $value)
    {
        if (empty($offset) && $offset !== 0) {
            $this->stack[] = $value;
        } else {
            $this->stack[$offset] = $value;
        }
        return $this;
    }

    /**
     * Gets value in collection
     *
     * @param mixed $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if (isset($this->stack[$offset])) {
            return $this->stack[$offset];
        } else {
            return null;
        }
    }

    /**
     * Does this value exist?
     *
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->stack[$offset]);
    }

    /**
     * Unsets value in collection
     *
     * @param mixed $offset
     *
     * @return $this
     */
    public function offsetUnset($offset)
    {
        unset($this->stack[$offset]);
        return $this;
    }

    /**
     * Set stack
     *
     * @param $stack
     * @return $this
     */
    protected function setStack($stack)
    {
        $this->stack = $stack;
        return $this;
    }

    /**
     * Get stack
     *
     * @return array
     */
    protected function getStack()
    {
        return $this->stack;
    }

    /**
     * Get number of elements in collection.
     *
     * @return int
     */
    public function count()
    {
        return count($this->getStack());
    }

    /**
     * Retrieves an iterator over this collection's stack.
     *
     * @return \Traversable
     */
    public function getIterator()
    {
        return new ArrayIterator($this->stack);
    }

    /**
     * Rewind
     * @return $this
     */
    public function rewind()
    {
        $this->position = 0;
        return $this;
    }

    /**
     * Current
     *
     * @return mixed
     */
    public function current()
    {
        return $this->stack[$this->position];
    }

    /**
     * Key
     *
     * @return mixed
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * @return array
     */
    public function keys()
    {
        return array_keys($this->getStack());
    }


    /**
     * Is the specified key defined in this collection?
     *
     * Regardless of whether its value is set to null.
     *
     * @param $key
     * @return bool
     */
    public function keyExists($key)
    {
        return array_key_exists($key, $this->stack);
    }

    /**
     * Gets first item in collection (regardless of its key)
     *
     * @return mixed|null
     */
    public function first()
    {
        foreach ($this as $item) {
            return $item;
            break;
        }
        return null;
    }

    /**
     * Get the last item in the collection
     *
     * @return mixed|null
     */
    public function last()
    {
        if (!$this->count()) {
            return null;
        }
        return \end($this->stack);
    }

    /**
     * Next
     */
    public function next()
    {
        ++$this->position;

        return $this;
    }

    /**
     * Valid
     *
     * @return bool
     */
    public function valid()
    {
        return isset($this->stack[$this->position]);
    }

    /* Getters/Setters
     */

    /**
     * Gets the name of the get method for this property.
     *
     * @param string $property The property name to retrieve.
     *
     * @return string
     */
    protected function getGetMethodName($property)
    {
        return 'get' . ucfirst($property);
    }

    /**
     * Returns first item in collection whose value of $field is $value.
     *
     * @param string $field The field name to compare against
     * @param mixed $value The value to compare against
     *
     * @return object
     */
    public function getBy($field, $value)
    {
        $method_name = $this->getGetMethodName($field);
        foreach ($this as $item) {
            $method = array($item, $method_name);
            if (is_callable($method) && call_user_func($method) == $value) {
                return $item;
            }
        }
        return null;
    }


    /**
     * This is a Collection-aware wrapper around array_column, for use in the Twig filter {{ | column($column) }}
     *
     * @param array $input
     * @param string $column
     * @throws InvalidArgumentException if the input given is not traversable
     * @return array
     */
    public static function array_column($input, $column)
    {
        if ($input instanceof self) {
            return $input->getColumnValues($column);
        }
        if (is_array($input)) {
            return array_column($input, $column);
        }
        throw new InvalidArgumentException("Invalid input given, must be array or Collection");
    }

    /**
     * Gets values for the given model *property* in the collection.
     *
     * TODO WARNING - Method is misnamed, this actually inspects the model 'property', not 'column'.
     *
     * @param string $property
     * @return array
     * @deprecated 17.5 Use 'getPropertyValues' instead. Retained for backwards comparability
     * @todo Remove 18.0
     */
    public function getColumnValues($property)
    {
        return $this->getPropertyValues($property);
    }

    /**
     * Gets values for the given model property in the collection.
     *
     * @param string $property
     * @return mixed[]|array
     */
    public function getPropertyValues($property)
    {
        $parts = [];
        $methodName = $this->getGetMethodName($property);
        foreach ($this as $item) {
            $method = [$item, $methodName];
            if (is_callable($method)) {
                $parts[] = $method();
            }
        }
        return $parts;
    }

    /**
     * Sorts the collection by a field and a direction. If the field values are
     * IComparable objects, then the object's compare method is used, otherwise
     * standard comparison is used.
     *
     * Supports multi-field comparision. Eg, 'sort by last name, then first name'
     *
     * Special case of $this->sortByCallback().
     *
     * @param string|string[]     $field                    The name of the field[s] to sort by.
     * @param string|string[]     $direction                The order[s] to sort by - 'asc' or 'desc'
     * @param callable|callable[] $compareFunction          If set, use this callable[s] as the comparison.
     *                                                      Only applied if values aren't directly comparable.
     * @param bool                $maintainIndexAssociation Should we preserve key association?
     *
     * @return $this
     */
    public function sortBy($field, $direction, $compareFunction = null, $maintainIndexAssociation = false)
    {
        $fields = (array)$field;
        $directions = (array)$direction;
        $compareFunctions = is_array($compareFunction) ? $compareFunction : array_fill(0, count($fields), null);

        $get_methods = [];
        foreach ($fields as $field) {
            $get_methods[] = $this->getGetMethodName($field);
        }

        $is_asc = [];
        foreach ($directions as $direction) {
            $is_asc[] = (strtolower($direction) === 'asc');
        }

        /** @var iterable $compareFunctions */
        $isCallable = [];
        foreach ($compareFunctions as $cf) {
            $isCallable[] = is_callable($cf);
        }

        $callback = function ($a, $b) use ($get_methods, $is_asc, $isCallable, $compareFunctions) {
            $result = 0;
            $i = 0;
            for ($iMax = count($get_methods); $i < $iMax; $i++) {
                $a_value = $a->{$get_methods[$i]}();
                $b_value = $b->{$get_methods[$i]}();

                if ($a_value instanceof \IComparable && $b_value instanceof \IComparable) {
                    $result = $a_value->compare($b_value);
                } elseif ($a_value !== $b_value) {
                    if ($isCallable[$i]) {
                        $result = $compareFunctions[$i]($a_value, $b_value);
                    } else {
                        $result = $a_value < $b_value ? -1 : 1;
                    }
                }

                // Have we determined a result ?
                if (0 !== $result) {
                    break;
                }
            }

            // At this point, if $result is 0, we didn't break and $i has incremented past the end of array.
            // Otherwise we broke at $i and return result at that position with requested polarity.
            return 0 === $result ? 0 : ($is_asc[$i] ? $result : -$result);
        };

        return $this->sortByCallback($callback, $maintainIndexAssociation);
    }


    /**
     * Convenience method to sort a collection using multiple fields
     *
     * @param array        $fields                   Keys = fieldName. Values = direction + optional callback. eg
     *                                               [  'lastName'  => ['asc'],
     *                                                  'firstName' => ['asc', $callable],
     *                                                  'dob'       => 'desc',   ]
     * @var   string|array $fields[]                 If value is string: Direction to sort. {'asc'|'desc'}
     * @var   string       $fields[][1]              If value is array:  1st item is direction,
     * @var   callable     $fields[][2]              2nd item is a compare function (optional)
     * @param bool         $maintainIndexAssociation Should we maintain key association?
     * @return $this
     */
    public function sortByMultiFields(array $fields, $maintainIndexAssociation = false)
    {
        $fieldNames = [];
        $directions = [];
        $compareFunctions = [];

        foreach ($fields as $fieldName => $fieldOptions) {
            $fieldNames[] = $fieldName;

            if (is_array($fieldOptions)) {
                $directions[] = $fieldOptions[0];
                $compareFunctions[] = $fieldOptions[1] ?: null;
            } else {
                $directions[] = $fieldOptions;
                $compareFunctions[] = null;
            }
        }

        return $this->sortBy($fieldNames, $directions, $compareFunctions, $maintainIndexAssociation);
    }


    /**
     * Sorts the collection by a callback. @see usort()
     *
     * @param callable $callback
     * @param bool     $maintainIndexAssociation Should we preserve key association?
     *
     * @return $this
     */
    public function sortByCallback($callback, $maintainIndexAssociation = false)
    {
        $stack = $this->getStack();
        if($maintainIndexAssociation){
            uasort($stack, $callback);
        } else {
            usort($stack, $callback);
        }
        $this->stack = $stack;
        return $this;
    }

    /**
     * Return the first N items from the Collection
     *
     * Does NOT mutate the collection you call this on.
     *
     * @param int|null $count           If null, returns ALL items.
     * @param bool     $preserveKeys
     *
     * @return static
     */
    public function limit($count = null, $preserveKeys = false)
    {
        return $this->slice(0, $count, $preserveKeys);
    }


    /**
     * Replace the internally held contents with the content of the given collection
     *
     * @param Collection $collection
     * @return $this
     */
    public function replaceWith(Collection $collection)
    {
        return $this->setStack($collection->getStack());
    }


    /**
     * Returns a new Collection containing the other collection's items merged with this one's.
     *
     * Does NOT mutate either this or the other Collection.
     *
     * @param Collection $other
     * @return static
     */
    public function merge(Collection $other)
    {
        $merged = $this->emptyClone();
        $merged->setStack(array_merge($this->getStack(), $other->getStack()));

        return $merged;
    }

    /**
     * Performs a union on the contents of the two collections, preserving keys.
     *
     * This behaves like the array union operator ($array1 + $array2).
     *
     * Does NOT mutate either this or the other Collection.
     *
     * @param Collection $other
     * @return static
     */
    public function union(Collection $other)
    {
        $merged = $this->emptyClone();
        $merged->setStack($this->getStack() + $other->getStack());
        return $merged;
    }


    /**
     * Returns a new collection containing this collection's items, plus any
     * items from the other collection not found in this collection.
     *
     * @param Collection $other - the second collection
     * @param string $column - a column on which to test uniqueness (ie. an ID)
     *                         If omitted, the entire object will be tested and may return an error "Fatal error: Nesting level too deep - recursive dependency?"
     *
     * @return static
     */
    public function mergeUnique(Collection $other, $column = null)
    {
        $merged = clone $this;
        if ($column) {
            $getColumn = $merged->getGetMethodName($column);
            $testValues = $merged->getColumnValues($column);
            foreach ($other as $item) {
                $value = $item->$getColumn();

                if(is_object($value) ){
                    throw new \UnexpectedValueException('Cannot compare objects');
                }

                if (!in_array($value, $testValues)) {
                    $merged->add($item);
                }
            }
        } else {
            foreach ($other as $item) {
                if(is_object($item) ){
                    throw new \UnexpectedValueException('Cannot compare objects');
                }

                if (!in_array($item, $merged->stack)) {
                    $merged->add($item);
                }
            }
        }
        return $merged;
    }


    /**
     * Returns a new collection containing unique items only
     *
     * @param string $column The column on which to test uniqueness
     * @return Collection
     * @throws \UnexpectedValueException
     */
    public function getUnique($column)
    {
        $results = $this->emptyClone();

        /* Exit early if the stack is empty; nothing to find
         * (~20-30 % faster when we do this)
         */
        if (!\count($this->stack)) {
            return $results;
        }

        $found = [];

        $getterMethod = $this->getGetMethodName($column);

        foreach ($this as $item) {
            $value = $item->$getterMethod();

            if (\is_object($value)) {
                throw new \UnexpectedValueException('Cannot compare objects');
            }

            if (!\in_array($value, $found, false)) {
                $results->add($item);
                $found[] = $value;
            }
        }

        return $results;
    }

    /**
     * Put an object in a particular place in the stack
     *
     * @param $index
     * @param $value
     * @return $this
     */
    public function setOffset($index, $value)
    {
        $this->stack[$index] = $value;
        return $this;
    }

    /**
     * Get the contents of a particular index in the stack
     *
     * @param $index
     * @return mixed
     */
    public function getOffset($index)
    {
        if (isset($this->stack[$index])) {
            return $this->stack[$index];
        }
        return null;
    }

    /**
     * Reindex the stack (useful when you setOffset() particular indexes and
     * want to restore 0-based indexes)
     * @return $this
     */
    public function reindex()
    {
        $this->stack = array_values($this->getStack());
        return $this;
    }

    /**
     * Diffs by a column.
     *
     * A shortcut for `$this->diff($otherCollection, $callbackWhichComparesField)`.
     *
     * @param Collection $otherCollection
     * @param string     $field
     *
     * @return static
     */
    public function diffBy(Collection $otherCollection, $field)
    {
        $getMethod = $this->getGetMethodName($field);
        return $this->diff(
            $otherCollection,
            function ($a, $b) use ($getMethod) {
                return $a->$getMethod() == $b->$getMethod();
            }
        );
    }

    /**
     * Returns a new collection containing items that are in $this, but not in
     * $otherCollection.
     *
     * @param  Collection $otherCollection
     * @param  callable $diffMethod The callback method to use for deciding
     *                                whether two items are the same.
     *                                Defaults to == comparison.
     * @return static
     */
    public function diff(Collection $otherCollection, $diffMethod = null)
    {
        $diff = $this->emptyClone();

        if (!is_callable($diffMethod)) {
            $diffMethod = function ($a, $b) {
                return $a == $b;
            };
        }

        foreach ($this as $item) {
            $inCollection = false;
            foreach ($otherCollection as $otherItem) {
                if (call_user_func($diffMethod, $item, $otherItem)) {
                    $inCollection = true;
                    break;
                }
            }
            if (!$inCollection) {
                $diff->add($item);
            }
        }
        return $diff;
    }


    /**
     * Returns a new collection containing items that are in both $this and
     * $otherCollection.
     *
     * @param Collection    $otherCollection
     * @param callable|null $compare The callback method to use for deciding
     *                               whether two items are the same.
     *                               Defaults to == comparison.
     * @return Collection
     */
    public function intersect(Collection $otherCollection, ?callable $compare = null): Collection
    {
        if (!is_callable($compare)) {
            $compare = static function ($a, $b) {
                return $a == $b;
            };
        }

        $result = $this->emptyClone();
        foreach ($this as $item) {
            foreach ($otherCollection as $otherItem) {
                if ($compare($item, $otherItem)) {
                    $result->add($item);
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * Applies a callable to all items in this collection, and returns a new
     * collection with the result. If $includeNull == false, iterations that
     * return explicitly null will be omitted from the final result
     *
     * @param  callback $callback
     * @param  bool $includeNull
     * @return Collection
     */
    public function map($callback, $includeNull = true)
    {
        $return = new Collection();
        foreach ($this as $key => $item) {
            $result = call_user_func($callback, $item, $key);
            if ($includeNull || !is_null($result)) {
                $return->offsetSet($key, $result);
            }
        }
        return $return;
    }

    /**
     * Filters out items that don't match $callable, and returns a new
     * collection with the result.
     *
     * @param  callback $callback
     * @return static
     */
    public function filter(callable $callback)
    {
        $return = $this->emptyClone();
        foreach ($this as $key => $item) {
            if (call_user_func($callback, $item, $key)) {
                $return->offsetSet($key, $item);
            }
        }
        return $return;
    }

    /**
     * Concatenates all collections within this collection into a single collection.
     *
     * @return Collection
     */
    public function concat()
    {
        return $this->reduce(
            function(Collection $return, Collection $item) {
                return $return->merge($item);
            },
            new Collection()
        );
    }

    /**
     * Reduces this collection into a single value.
     *
     * @param callable $callback
     * @param mixed    $initial
     * @return mixed
     */
    public function reduce(callable $callback, $initial)
    {
        $return = $initial;
        foreach ($this as $item) {
            $return = $callback($return, $item);
        }
        return $return;
    }

    /**
     * Groups all results by a field value.
     *
     * @param  string $field The field to search by.
     * @return Collection|static[] @see $this->groupByCallback()
     */
    public function groupBy($field)
    {
        $get_method = $this->getGetMethodName($field);
        return $this->groupByCallback(
            function ($item) use ($get_method) {
                return $item->$get_method();
            }
        );
    }

    /**
     * Groups results by the return value of $callback.
     *
     * @param  callable $callback A callback which takes each item within $this
     *                            as its only parameter, and returns a key.
     * @return Collection|static[]
     *   A collection of collections.
     *   * key:   result of $item->$callback()
     *   * value: a collection of all items in $this where $item->$callback() = $key
     */
    public function groupByCallback($callback)
    {
        $result = new Collection();

        foreach ($this as $key => $item) {
            $newKey = call_user_func($callback, $item, $key);
            if (isset($newKey)) {
                if (!isset($result[$newKey])) {
                    $result[$newKey] = $this->emptyClone();
                }
                $result[$newKey]->add($item);
            }
        }

        return $result;
    }

    /**
     * Returns a slice of this collection from the specified offset, length optionally specified.
     *
     * Does NOT mutate the original, enabling the caller to cut multiple slices from a Collection.
     *
     * @param int $offset
     * @param int $count
     * @param bool $preserve_keys Whether to preserver array keys or re-index from 0
     * @return static
     */
    public function slice($offset, $count = null, $preserve_keys = false)
    {
        $copy = $this->emptyClone();
        $copy->stack = array_slice($this->stack, $offset, $count, $preserve_keys);
        return $copy;
    }


    /**
     * @param string $field
     * @param mixed $value
     * @return static
     */
    public function filterBy($field, $value)
    {
        $getField = $this->getGetMethodName($field);
        return $this->filter(
            function ($item) use ($getField, $value) {
                return $item->$getField() == $value;
            }
        );
    }


    /**
     * Indexes the collection by the given column.
     * Be careful! The property indicated by $column should contain unique values. If not, earlier values will be overwritten.
     *
     * @param string $column
     * @return $this
     */
    public function indexBy($column)
    {
        $callback = function ($item) use ($column) {
            return call_user_func([$item, $this->getGetMethodName($column)]);
        };

        return $this->indexByCallback($callback);
    }

    /**
     * Indexes the collection by the given callback.
     *
     * Be careful! The value from the callback should contain values unique for the collection.
     * If not, earlier values will be overwritten.
     *
     * @param callable $callback
     * @return $this
     */
    public function indexByCallback(callable $callback)
    {
        $new = [];
        foreach($this->stack as $key => $item){
            $newKey = call_user_func($callback, $item, $key);
            $new[$newKey] = $item;
        }
        // Bypass GenericCollection type-check in setStack()
        $this->stack = $new;
        return $this;
    }

    /**
     * Creates a deep clone of this collection.
     *
     * @return static
     */
    public function deepClone()
    {
        $clone = $this->emptyClone();
        foreach ($this as $key => $value) {
            if (is_object($value)) {
                $clone[$key] = clone $value;
            }
        }
        return $clone;
    }


    /**
     * Creates a shallow clone of this collection.
     *
     * Enables preservation of indexing and sort order, but does
     * not duplicate/clone objects in collection
     *
     * @return static
     */
    public function shallowClone()
    {
        $newCollection = $this->emptyClone();
        foreach($this->stack as $idx => $item){
            $newCollection->stack[$idx] = $item;
        }
        return $newCollection;
    }


    /**
     * @return array
     */
    public function getDefaultJsonMap(): array
    {
        // this happens to be ignored by this particular implementation
        return [];
    }


    /**
     * Specify data which should be serialized to JSON. Currently, attempts to
     * serialize all items within the stack.
     *
     * @param IDecorator[] $decorators
     * @return array
     */
    public function jsonSerialize(IDecorator... $decorators)
    {
        if (\count($decorators) === 0) {
            return $this->getStack();
        }

        // if a map is specified, we want to explicitly pass it through to sub-objects where possible

        $json = [];
        $i = 0;
        foreach ($this as $value) {
            foreach ($decorators as $decorator) {
                $json[$i] = $decorator->decorate($json[$i] ?: [], $value);

            }
            $i++;
        }

        return $json;
    }

}
