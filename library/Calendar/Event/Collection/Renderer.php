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
	protected $startTimestamp = -1;

	/**
	 * A unix timestamp
	 *
	 * @var int
	 */
	protected $endTimestamp = -1;

	/**
	 * An instance of sitepage
	 *
	 * @var null
	 */
	protected $page = null;

	/*
	 */
	/**
	 * Mega-stack Calendar_Event_Collection instance which stores all events
	 * An instance of Calendar_Event_Collection
	 *
	 * @var null
	 */
	protected $allEvents = null;

	/**
	 * Constructor
	 *
	 * @param $api
	 * @param $startTimestamp
	 * @param $endTimestamp
	 * @param $page
	 */
	public function __construct($api, $startTimestamp, $endTimestamp, $page) {
		$this->setApi($api);
		$this->setStartTimestamp($startTimestamp);
		$this->setEndTimestamp($endTimestamp);
		$this->setPage($page);

		$this->setAllEvents(new Calendar_Event_Collection());

		$this->collectAllEvents();
	}

	/**
	 * Collects all events from everywhere, for the current day
	 */
	protected function collectAllEvents() {
		$eventList = $this->getApi()->getEvents($this->getStartTimestamp(), $this->getEndTimestamp());

		/* Only attempt to merge collections
		 */
		if (is_a($eventList, 'Calendar_Event_Collection')) {
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
		$collection = $this->getAllEvents();

		$registry = array();

		foreach($collection as $currentEvent) {

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
	 * @param Calendar_Api_EventType_Interface $api
	 */
	protected function setApi($api) {
		$this->api = $api;
	}

	/**
	 * Get api
	 *
	 * @return Calendar_Api_EventType_Interface
	 */
	protected function getApi() {
		return $this->api;
	}

	/**
	 * Set end timestamp
	 *
	 * @param int $endTimestamp
	 */
	protected function setEndTimestamp($endTimestamp) {
		$this->endTimestamp = $endTimestamp;
	}

	/**
	 * Get end timestamp
	 *
	 * @return int
	 */
	protected function getEndTimestamp() {
		return $this->endTimestamp;
	}

	/**
	 * Set start timestamp
	 *
	 * @param int $startTimestamp
	 */
	protected function setStartTimestamp($startTimestamp) {
		$this->startTimestamp = $startTimestamp;
	}

	/**
	 * Get start timestamp
	 *
	 * @return int
	 */
	protected function getStartTimestamp() {
		return $this->startTimestamp;
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
