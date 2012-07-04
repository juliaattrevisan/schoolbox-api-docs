<?php
/**
 * Calendar_Event_Collection_Iterator
 *
 * This is actually a pretty generic iterator implementation that isn't necessarily restricted
 * just to the calendar at time of writing. It can be used on various data types. It's only here
 * to keep calendar stuff all self-contained.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Event_Collection_Iterator implements Iterator {
	private $stack = null;
	private $position = 0;

	/**
	 * Constructor
	 *
	 * @param Calendar_Event_Collection $stack
	 */
	public function __construct($stack) {
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
	private function setStack($stack) {
		$this->stack = $stack;
	}
}
