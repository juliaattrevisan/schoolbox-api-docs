<?php
/**
 * Base collection of model objects.
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;
use Schoolbox;

class Collection implements \ICollection {

    protected $stack = array();
	protected $position = 0;

    /**
     * @param $element
     */
    public function add($element) {
        $this->stack[] = $element;
    }

    /**
     * Constructor
     *
     * @param array $stack
     */
    public function __construct($stack = array()) {
        $this->setStack($stack);
    }

    /**
     * Rewind
     */
    public function rewind() {
        $this->position = 0;
    }

    /**
     * Current
     *
     * @return mixed
     */
    public function current() {
        return $this->stack[$this->position];
    }

    /**
     * Key
     *
     * @return mixed
     */
    public function key() {
        return $this->position;
    }

    /**
     * Next
     */
    public function next() {
        ++$this->position;
    }

    /**
     * Valid
     *
     * @return bool
     */
    public function valid() {
        return isset($this->stack[$this->position]);
    }

    /* Getters/Setters
     */

    /**
     * Set stack
     *
     * @param $stack
     */
    protected function setStack($stack) {
        $this->stack = $stack;
    }

    /**
     * Get stack
     *
     * @return array
     */
    protected function getStack() {
        return $this->stack;
    }

    /**
     * Get number of elements in collection.
     *
     * @return array
     */
    public function count() {
        return count($this->getStack());
    }

	/**
	 * Gets the name of the get method for this field.
	 *
	 * @param string $field The field name to retrieve.
	 *
	 * @return string
	 */
	protected function getGetMethodName($field) {
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
	public function getBy($field, $value) {
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
	public function getColumnValues($column) {
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
	public function sortBy($field, $direction) {
		$get_method = $this->getGetMethodName($field);
		$is_asc     = (strtolower($direction) === 'asc');
		$callback   = function($a, $b) use ($get_method, $is_asc) {
			$result  = 0;
			$a_value = $a->$get_method();
			$b_value = $b->$get_method();
			if ($a_value instanceof \IComparable && $b_value instanceof \IComparable) {
				$result = $a_value->compare($b_value);
			}
			elseif ($a_value !== $b_value) {
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
	public function sortByCallback($callback) {
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
	public function limit($offset = 0, $count = null) {
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
	public function merge(Collection $other) {
		$this->setStack(array_merge($this->getStack(), $other->getStack()));
		return $this;
	}

	/**
	 * Put an object in a particular place in the stack
	 *
	 * @param $index
	 * @param $value
	 */
	public function setOffset($index, $value) {
		$this->stack[$index] = $value;
	}

	/**
	 * Get the contents of a particular index in the stack
	 *
	 * @param $index
	 * @return mixed
	 */
	public function getOffset($index) {
		return $this->stack[$index];
	}

	/**
	 * Reindex the stack (useful when you setOffset() particular indexes and
	 * want to restore 0-based indexes)
	 */
	public function reindex() {
		$this->setStack(array_values($this->getStack()));
	}
}
