<?php
/**
 * Base collection of model objects.
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;

use ArrayAccess;
use Schoolbox;
use ArrayIterator;
use ICollection;
use JsonSerializable;

class Collection implements ICollection, JsonSerializable, ArrayAccess
{
    protected $stack = array();
    protected $position = 0;

    /**
    * Constructor
    *
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
     * Prepend an element to the start of the stack
     *
     * @param $element
     */
    public function prepend($element)
    {
        array_unshift($this->stack, $element);
    }

    /**
    * Returns whole collection as an array.
    *
    * @return array
    */
    public function getAll()
    {
        return $this->stack;
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
        if (empty($offset)) {
            $this->stack[] = $value;
        } else {
            $this->stack[$offset] = $value;
        }
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
    * @return void
    */
    public function offsetUnset($offset)
    {
        unset($this->stack[$offset]);
    }

    /**
    * Set stack
    *
    * @param $stack
    */
    protected function setStack($stack)
    {
        $this->stack = $stack;
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
    * @return array
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
     */
    public function rewind()
    {
        $this->position = 0;
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
    * Gets first item in collection (regardless of its key)
    *
    * @return mixed
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
    * @throws \Exception
    * @return mixed
    */
    public function last()
    {
        if ($this->count()) {
            return $this->stack[count($this->stack)-1];
        }

        return null;
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
    * Gets the name of the get method for this field.
    *
    * @param string $field The field name to retrieve.
    *
    * @return string
    */
    protected function getGetMethodName($field)
    {
        return 'get'.ucfirst($field);
    }

    /**
    * Returns first item in collection whose value of $field is $value.
    *
    * @param string $field The field name to compare against
    * @param mixed  $value The value to compare against
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
    * Gets values for the given column in the collection.
    *
    * @param string $column
    *
    * @return array
    */
    public function getColumnValues($column)
    {
        $parts       = array();
        $method_name = $this->getGetMethodName($column);
        foreach ($this as $item) {
            $method = array($item, $method_name);
            if (is_callable($method)) {
                $parts[] = call_user_func($method);
            }
        }
        return $parts;
    }

    /**
    * Sorts the collection by a field and a direction. If the field values are
    * IComparable objects, then the object's compare method is used, otherwise
    * standard comparison is used.
    *
    * Special case of $this->sortByCallback().
    *
    * @param string $field     The name of the field to sort by.
    * @param string $direction The order to sort by - 'asc' or 'desc'
    *
    * @return $this
    */
    public function sortBy($field, $direction)
    {
        $get_method = $this->getGetMethodName($field);
        $is_asc     = (strtolower($direction) === 'asc');
        $callback   = function($a, $b) use ($get_method, $is_asc) {
            $result  = 0;
            $a_value = $a->$get_method();
            $b_value = $b->$get_method();
            if ($a_value instanceof \IComparable && $b_value instanceof \IComparable) {
                $result = $a_value->compare($b_value);
            } elseif ($a_value !== $b_value) {
                $result = $a_value < $b_value ? -1 : 1;
            }
            return $is_asc ? $result : -$result;
        };
        return $this->sortByCallback($callback);
    }

    /**
    * Sorts the collection by a callback. @see usort()
    *
    * @param callable $callback
    *
    * @return $this
    */
    public function sortByCallback($callback)
    {
        $stack = $this->getStack();
        usort($stack, $callback);
        $this->setStack($stack);
        return $this;
    }

    /**
    * Limits the collection by count and offset. @see array_slice()
    *
    * @param int $offset
    * @param int $count
    *
    * @return $this
    */
    public function limit($offset = 0, $count = null)
    {
        $this->setStack(array_slice($this->getStack(), $offset, $count));
        return $this;
    }

    /**
    * Merges contents of another collection with this one - appends all
    * elements in the other collection to this one.
    *
    * @param Collection $other
    *
    * @return $this
    */
    public function merge(Collection $other)
    {
        $this->setStack(array_merge($this->getStack(), $other->getStack()));
        return $this;
    }


    /**
    * Merges contents of another collection with this one where duplicate elements
    * are ignored. Preference is given to the collection on which the method is called.
    *
    * @param Collection $other - the second collection
    * @param string $column    - a column on which to test uniqueness (ie. an ID)
    *                            If omitted, the entire object will be tested
    * @return self
    */
    public function mergeUnique(Collection $other, $column = null)
    {
        if ($column) {
            $getColumn = $this->getGetMethodName($column);
            $testValues = $this->getColumnValues($column);
            foreach ($other as $item) {
                $value = $item->$getColumn();
                if (!in_array($value, $testValues)) {
                    $this->add($item);
                }
            }
        } else {
            foreach ($other as $item) {
                if (!in_array($item, $this->stack)) {
                    $this->add($item);
                }
            }
        }
        return $this;
    }

    /**
    * Put an object in a particular place in the stack
    *
    * @param $index
    * @param $value
    */
    public function setOffset($index, $value)
    {
        $this->stack[$index] = $value;
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
    */
    public function reindex()
    {
        $this->setStack(array_values($this->getStack()));
    }

    /**
    * Returns a new collection containing items that are in $this, but not in
    * $otherCollection.
    *
    * @param  Collection $otherCollection
    * @param  callable   $diffMethod The callback method to use for deciding
    *                                whether two items are the same.
    *                                Defaults to == comparison.
    * @return Collection
    */
    public function diff(Collection $otherCollection, $diffMethod = null)
    {
        $diff = new Collection();

        if (!is_callable($diffMethod)) {
            $diffMethod = function($a, $b) {
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
    * Applies a callabale to all items in this collection, and returns a new
     * collection with the result. If $includeNull == false, iterations that
     * return explicitly null will be omitted from the final result
    *
    * @param  callback   $callback
     * @param  bool       $includeNull
    * @return Collection
    */
    public function map($callback, $includeNull = true)
    {
        $return = new Collection();
        foreach ($this as $key => $item) {
            $result = call_user_func($callback, $item);
            if ($includeNull || !is_null($result)) {
                $return->offsetSet($key, $result);
            }
        }
        return $return;
    }

    /**
    * Filters out items that don't match $callbale, and returns a new
    * collection with the result.
    *
    * @param  callback   $callback
    * @return Collection
    */
    public function filter($callback)
    {
        $return = new Collection();
        foreach ($this as $key => $item) {
            if (call_user_func($callback, $item)) {
                $return->offsetSet($key, $item);
            }
        }
        return $return;
    }

    /**
    * Groups all results by a field value.
    *
    * @param  string $field The field to search by.
    * @return Collection|Collection[] @see $this->groupByCallback()
    */
    public function groupBy($field)
    {
        $get_method = $this->getGetMethodName($field);
        return $this->groupByCallback(
            function($item) use ($get_method) {
                return $item->$get_method();
            }
        );
    }

    /**
    * Groups results by the return value of $callback.
    *
    * @param  callable $callback A callback which takes each item within $this
    *                            as its only parameter, and returns a key.
    * @return Collection|Collection[]
    *   A collection of collections.
    *   * key:   result of $item->$callback()
    *   * value: a collection of all items in $this where $item->$callback() = $key
    */
    public function groupByCallback($callback)
    {
        /** @var Collection[] $result */
        $result = array();
        foreach ($this as $item) {
            $key = call_user_func($callback, $item);
            if (!array_key_exists($key, $result)) {
                $result[$key] = new Collection();
            }
            $result[$key]->add($item);
        }
        return new Collection($result);
    }

    /**
    * Slice this collection to a specified size, optionally with an offset
    *
    * @param int $offset
    * @param int $count
    * @return self
    */
    public function slice($offset, $count = null)
    {
        $this->stack = array_slice($this->stack, $offset, $count, false);
        return $this;
    }

    public function filterBy($field, $value)
    {
        $getField = $this->getGetMethodName($field);
        return $this->filterByCallback(
            function($item) use ($getField, $value) {
                return $item->$getField() == $value;
            }
        );
    }


    /**
    * Filters out elements from this collection based on the given callback
    *
    * @deprecated use filter() instead
    * @param callable $callback {
    *                      param $item
    *                      return bool
    *                 }
    *
    * @return Collection
    */
    public function filterByCallback($callback)
    {
        $result = array();
        foreach ($this as $item) {
            if (call_user_func($callback, $item)) {
                $result[] = $item;
            }
        }
        return new Collection($result);
    }

    /**
    * Specify data which should be serialized to JSON. Currently, attempts to
    * serialize all items within the stack.
    *
    * @return array
    */
    public function jsonSerialize()
    {
        return $this->getStack();
    }


    /**
     * Indexes the collection by the given column.
     * Be careful! The property indicated by $column should contain unique values. If not, earlier values will be overwritten.
     *
     * @param string $column
     * @return self
     */
    public function indexBy($column)
    {
        $this->reindex();

        $keys = $this->getColumnValues($column);
        $len  = count($keys);
        $new  = array();

        for ($i = 0; $i < $len; ++$i) {
            $new[$keys[$i]] = $this->stack[$i];
        }

        $this->setStack($new);

        return $this;
    }

}
