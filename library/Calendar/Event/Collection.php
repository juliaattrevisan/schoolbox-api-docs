<?php
/**
 * Calendar_Event_Collection
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Event_Collection {
	private $stack = array();
	private $userHandler = null;

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
		$this->setUserHandler(new User());

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

	/* Getters/Setters
	 */

	/**
	 * Set user handler
	 *
	 * @param $userHandler
	 */
	private function setUserHandler($userHandler) {
		$this->userHandler = $userHandler;
	}

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
	 * Get user handler
	 *
	 * @return null
	 */
	private function getUserHandler() {
		return $this->userHandler;
	}

	/**
	 * Add an Calendar_Event_Abstract-typed element to the stack
	 *
	 * @param $element
	 */
	public function add(Calendar_Event_Abstract $element) {
		$this->stack[] = $element;
	}

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
	 * Get stack
	 *
	 * @return array
	 */
	private function getStack() {
		return $this->stack;
	}
}
