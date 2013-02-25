<?php
/**
 * Calendar_Event_Collection_Renderer
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Event_Collection_Renderer {
	protected $interfaceClass = 'Calendar_Api_EventType_Interface';

	/**
	 * An instance of a Calendar API class
	 *
	 * @var null
	 */
	protected $api = null;

	/**
	 * A unix timestamp
	 *
	 * @var int
	 */
	protected $currentDay = -1;

	/**
	 * An instance of sitepage
	 *
	 * @var null
	 */
	protected $page = null;

	/* Mega-stack Calendar_Event_Collection instance which stores all events
	 */
	protected $allEvents = null;

	/**
	 * Constructor
	 *
	 * @param $api
	 * @param $currentDay
	 * @param $page
	 */
	public function __construct($api, $currentDay, $page) {
		$this->setApi($api);
		$this->setCurrentDay($currentDay);
		$this->setPage($page);

		$this->setAllEvents(new Calendar_Event_Collection());

		$this->collectAllEvents();
	}

	/**
	 * Collects all events from everywhere, for the current day
	 *
	 * Do this by using ReflectionClass on the API Interface to
	 * get all the mandatory methods
	 */
	protected function collectAllEvents() {
		$reflection = new ReflectionClass($this->getInterfaceClass());

		foreach ($reflection->getMethods() as $currentMethod) {
			$methodName = $currentMethod->getName();

			$eventList = $this->getApi()->$methodName($this->getCurrentDay());

			$this->getAllEvents()->merge($eventList);
		}

		/* Sort all the events in order of time
		 */
		$this->getAllEvents()->sort();

	}

	/**
	 * Render all events
	 */
	public function renderAll() {
		$iterator = $this->getAllEvents()->createIterator();

		$registry = array();

		for ($iterator->rewind(); $iterator->valid(); $iterator->next()) {
			$currentEvent = $iterator->current();

			$rendererClass = $currentEvent->getRendererClass();

			if (!$registry[$rendererClass]) {
				$registry[$rendererClass] = new $rendererClass($this->getPage());
			}

			$currentEvent->accept($registry[$rendererClass]);
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set api
	 *
	 * @param $api
	 */
	protected function setApi($api) {
		$this->api = $api;
	}

	/**
	 * Get api
	 *
	 * @return null
	 */
	protected function getApi() {
		return $this->api;
	}

	/**
	 * Set current day
	 *
	 * @param $currentDay
	 */
	protected function setCurrentDay($currentDay) {
		$this->currentDay = $currentDay;
	}

	/**
	 * Get current day
	 *
	 * @return int
	 */
	protected function getCurrentDay() {
		return $this->currentDay;
	}

	/**
	 * Set page
	 *
	 * @param $page
	 */
	protected function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return null
	 */
	protected function getPage() {
		return $this->page;
	}

	/**
	 * Set all events
	 *
	 * @param Calendar_Event_Collection $allEvents
	 */
	protected function setAllEvents($allEvents) {
		$this->allEvents = $allEvents;
	}

	/**
	 * Get all events
	 *
	 * @return Calendar_Event_Collection
	 */
	protected function getAllEvents() {
		return $this->allEvents;
	}

	/**
	 * The interface which defines accessible methods
	 *
	 * @return string
	 */
	public function getInterfaceClass() {
		return $this->interfaceClass;
	}
}
