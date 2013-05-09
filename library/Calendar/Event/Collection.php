<?php
/**
 * Calendar_Event_Collection
 *
 * A container for subclasses of Calendar_Event_Abstract.
 *
 * Uses the Iterator to traverse in a standardised interface. This allows you to make a more
 * complex/different data structure instead of the generic array() for the $stack, and still
 * render its contents using the same methodology.
 *
 * I am debating whether the instance of Iterator should be within or without the class. I've
 * seen usages of both. Having it within makes it a bit more self-contained but less reusable.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Event_Collection implements Iterator {

	/* A bunch of Calendar_Event_Type_Abstract subclasses
	 */
	private $stack = array();
	private $position = 0;

	/* Cached set of colours
	 */
	private $colourSet = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->cacheColourSet();

		$this->setup();
	}

	/**
	 * Hook method
	 */
	protected function setup() {
	}

	/**
	 * Cache colour set
	 *
	 * Temporarily disabled for now
	 */
	private function cacheColourSet() {
//		$colours = array();
//
//		$query = "SELECT eventTypeID, eventColour FROM luEventType";
//		$result = $this->db->query($query);
//
//		while($row = $this->db->getNextArray($result) ) {
//			$colours[$row['eventTypeID']] = '#' . $row['eventColour'];
//		}
//
//		$this->setColourSet($colours);
	}

	/**
	 * The number of items in this collection
	 *
	 * @return int
	 */
	public function getCount() {
		return count($this->getStack());
	}

	/**
	 * If the collection has at least one item in it,
	 * return the first element in the stack
	 *
	 * @return mixed
	 */
	public function getFirst() {
		if ($this->getCount()) {
			$stack = $this->getStack();

			return $stack[0];
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set colour set
	 *
	 * @param $colourSet
	 */
	private function setColourSet($colourSet) {
		$this->colourSet = $colourSet;
	}

	/**
	 * Get colour set
	 *
	 * @return array
	 */
	private function getColourSet() {
		return $this->colourSet;
	}

	/**
	 * Add an Calendar_Event_Abstract-typed element to the stack
	 *
	 * @param $element
	 */
	public function add(Calendar_Event_Type_Abstract $element) {
		$this->stack[] = $element;
	}

	/**
	 * Merge another stack into this one
	 *
	 * @param \Calendar_Event_Collection $collection
	 */
	public function merge(Calendar_Event_Collection $collection) {
		$merged = array_merge($this->getStack(), $collection->getStack());

		$this->setStack($merged);
	}

	/**
	 * Comparison - sorts on start time, then end time, then finally name.
	 * Also, all day events are always before timed events.
	 *
	 * @param $a
	 * @param $b
	 * @return int
	 */
	private function cmp($a, $b) {
		$a_allday = $a->getAllday();
		$b_allday = $b->getAllday();
		if ($a_allday != $b_allday) {
			return $a_allday ? -1 : 1;
		}
		if ($a->getStartTime() instanceof Alaress_Date_Format_Interface
			&& $b->getStartTime() instanceof Alaress_Date_Format_Interface
		) {
			$a_timestamp = $a->getStartTime()->getUnixTimestamp();
			$b_timestamp = $b->getStartTime()->getUnixTimestamp();
			if ($a_timestamp < $b_timestamp) {
				return -1;
			}
			else if ($a_timestamp > $b_timestamp) {
				return 1;
			}
		}
		if ($a->getEndTime() instanceof Alaress_Date_Format_Interface
			&& $b->getEndTime() instanceof Alaress_Date_Format_Interface
		) {
			$a_timestamp = $a->getEndTime()->getUnixTimestamp();
			$b_timestamp = $b->getEndTime()->getUnixTimestamp();
			if ($a_timestamp < $b_timestamp) {
				return -1;
			}
			else if ($a_timestamp > $b_timestamp) {
				return 1;
			}
		}
		return strcmp($a->getNote(), $b->getNote());
	}

	/**
	 * Sort the stack
	 *
	 * Used after merging multiple stacks so all events are ordered by time
	 */
	public function sort() {
		$sorted = $this->getStack();

		usort($sorted, array($this, 'cmp'));

		$this->setStack($sorted);
	}

	/* Getters/Setters
	 */


	/**
	 * Get iterator
	 *
	 * @return null
	 */
	public function getIterator() {
		return $this;
	}

	/**
	 * Set stack
	 *
	 * @param $stack
	 *
	 * @return array
	 */
	public function setStack($stack) {
		$this->stack = $stack;
	}

	/**
	 * Get stack
	 *
	 * @return array
	 */
	public function getStack() {
		return $this->stack;
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




}
