<?php
/**
 * Collection intended for column->property mappings for a mapper class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox;
use CallbackFilterIterator;

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

}