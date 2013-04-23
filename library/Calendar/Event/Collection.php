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
class Calendar_Event_Collection {

	/* A bunch of Calendar_Event_Type_Abstract subclasses
	 */
	private $stack = array();

	/* Cached set of colours
	 */
	private $colourSet = array();

	/* Iterator class to manage traversal
	 */
	private $iterator = null;

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
	 * Instantiate an iterator
	 *
	 * @return null
	 */
	public function createIterator() {
		$this->setIterator(new Calendar_Event_Collection_Iterator($this->getStack()));

		return $this->getIterator();
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
	 *
	 * @param $a
	 * @param $b
	 * @return int
	 */
	private function cmp($a, $b) {
		$a_timestamp = $a->getStartTime()->getUnixTimestamp();
		$b_timestamp = $b->getStartTime()->getUnixTimestamp();
		if ($a_timestamp < $b_timestamp) {
			return -1;
		}
		else if ($a_timestamp > $b_timestamp) {
			return 1;
		}

		$a_timestamp = $a->getEndTime()->getUnixTimestamp();
		$b_timestamp = $b->getEndTime()->getUnixTimestamp();
		if ($a_timestamp < $b_timestamp) {
			return -1;
		}
		else if ($a_timestamp > $b_timestamp) {
			return 1;
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
	 * Set iterator
	 *
	 * @param $iterator
	 *
	 * @return null
	 */
	public function setIterator($iterator) {
		$this->iterator = $iterator;
	}

	/**
	 * Get iterator
	 *
	 * @return null
	 */
	public function getIterator() {
		return $this->iterator;
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
}
