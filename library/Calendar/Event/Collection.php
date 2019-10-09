<?php
/**
 * Collection
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
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link		https://schoolbox.com.au/
 */

namespace Calendar\Event;
use Calendar\Event\Type\EventBase;
use Calendar\Event\Type\EventInterface;


/**
 * @deprecated Use a standard collection instead
 */
class Collection implements \Iterator, \Countable {

	/**
	 * A bunch of EventBase subclasses
	 *
	 * @var array
	 */
	private $stack = array();

    /**
     * @var int
     */
    private $position = 0;

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
	 * @return EventBase $element
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
	 * Add an Calendar_Event_Abstract-typed element to the stack
	 *
	 * @param EventInterface $element
	 */
	public function add(EventInterface $element) {
		$this->stack[] = $element;
	}

    /**
     * Merge another stack into this one
     *
     * @param Collection $collection
     * @return $this
     */
	public function merge(Collection $collection) {
		$merged = array_merge($this->getStack(), $collection->getStack());

		$this->setStack($merged);

        return $this;
	}

	/**
	 * Merge another stack into this one, but ensures that duplicate events are
	 * not added to the stack again.
	 *
	 * @param Collection $collection
	 */
	public function mergeWithoutDuplicates(Collection $collection) {
		$id_list = array();
		foreach ($this as $event) {
			/** @var EventInterface $event */
			$id_list[$event->getEventId()] = true;
		}

        /** @var EventInterface $event */
		foreach ($collection as $event) {
			$id = $event->getEventId();
			//
			// No ID for this event? it can't be identified as a duplicate or
			// not, so assume it's not one.
			if (empty($id) || !array_key_exists($id, $id_list)) {
				$this->add($event);
			}
		}
	}

	/**
	 * Comparison - sorts on day of event, start time, end time, then finally name.
	 * Also, all day events are always before timed events on the same day.
	 *
	 * @param EventBase $a
	 * @param EventBase $b
	 * @return int
	 */
	private function cmp(EventBase $a, EventBase $b) {
		//
		// Sort on day of event only (not time)
		$a_day = $a->getStartTime()->getUsingFormat('Ymd');
		$b_day = $b->getStartTime()->getUsingFormat('Ymd');
		if ($a_day < $b_day) {
			return -1;
		}
		else if ($a_day > $b_day) {
			return 1;
		}

		//
		// Events on same day - all day events always before timed events
		$a_allday = $a->getAllday();
		$b_allday = $b->getAllday();
		if ($a_allday != $b_allday) {
			return $a_allday ? -1 : 1;
		}

		//
		// Sort on start time of event
		$a_timestamp = $a->getStartTime()->getUnixTimestamp();
		$b_timestamp = $b->getStartTime()->getUnixTimestamp();
		if ($a_timestamp < $b_timestamp) {
			return -1;
		}
		else if ($a_timestamp > $b_timestamp) {
			return 1;
		}

		//
		// Sort on end time of event
		$a_timestamp = $a->getEndTime()->getUnixTimestamp();
		$b_timestamp = $b->getEndTime()->getUnixTimestamp();
		if ($a_timestamp < $b_timestamp) {
			return -1;
		}
		else if ($a_timestamp > $b_timestamp) {
			return 1;
		}
		return strcmp($a->getTitle(), $b->getTitle());
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

	/**
	 * Filters out items from the collection.
	 *
	 * @param callable $callback The callback to call against each collection
	 *                           element - if it returns false, it's removed.
	 */
	public function filter($callback) {
		if (is_callable($callback)) {
			$this->setStack(array_values(array_filter($this->getStack(), $callback)));
		}
	}

	/* Getters/Setters
	 */


	/**
	 * Get iterator
	 *
	 * @return Collection
	 */
	public function getIterator() {
		return $this;
	}

	/**
	 * Set stack
	 *
	 * @param array $stack
	 *
	 * @return array
	 */
	public function setStack($stack) {
		$this->stack = $stack;
	}

	/**
	 * Get stack
	 *
	 * @return array|EventBase[]
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
	 * @return EventBase
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

	/**
	 * Count
	 *
	 * @return int
	 */
	public function count() {
		return count($this->stack);
	}

}
