<?php
/**
 * Collection intended for column->property mappings for a mapper class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox;
use BadMethodCallException;
use CallbackFilterIterator;

/**
 * TODO - this probably shouldn't extent Collection anymore, as many Collection methods will break this class's internals
 */
class MappingCollection extends Collection {

	/**
	 * Retrieves an iterator which filters out any element in the collection
	 * which doesn't pass $this->accept().
	 *
	 * @return \Traversable
	 */
	public function getIterator() {
		return new CallbackFilterIterator(parent::getIterator(), array($this, 'accept'));
	}

	/**
	 * Filters out disabled keys from the mapping.
	 *
	 * * Keys without an "enabled" field default to being enabled
	 * * Keys with a callback as the enabled field are enabled if callback
	 *   returns true
	 * * Keys with any other "enabled" field are enabled if that field == true
	 *
	 * @param array     $current
	 * @param int       $key
	 * @param \Iterator $iterator
	 *
	 * @return bool
	 */
	public function accept($current, $key, $iterator) {
		if (!isset($current['enabled'])) {
			return true;
		}
		if (is_callable($current['enabled'])) {
			return $current['enabled']();
		}
		return $current['enabled'];
	}

	/**
	 * Allows retrieval of an offset by its database column, instead of its
	 * property name.
	 *
	 * @param  string $column
	 * @return array
	 */
	public function getOffsetByColumn($column) {
		foreach ($this as $map) {
			if ($map['column'] === $column) {
				return $map;
			}
		}
		return null;
	}


    /**
     * Not supported. This collection must be keyed by model property
     *
     * @param $element
     * @return $this
     * @throws BadMethodCallException
     */
    public function add($element): self
    {
        throw new BadMethodCallException('This collection must be keyed');
    }


    /**
     * @param $element
     * @return Collection|void
     * @throws BadMethodCallException
     */
    public function prepend($element)
    {
        throw new BadMethodCallException('This collection must be keyed');
    }
}
