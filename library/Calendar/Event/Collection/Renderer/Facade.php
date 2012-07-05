<?php
/**
 * Calendar_Event_Collection_Renderer_Facade
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Event_Collection_Renderer_Facade {
	private $api = null;
	private $currentDay = -1;
	private $page = null;

	private $types = array(
		'Day',
		'StudentBirthday',
		'Submission',
	);

	private $registry = array();

	/**
	 * Constructor
	 *
	 * @param $api
	 * @param $currentDay
	 * @param $page
	 */
	public function __construct($api, $currentDay, $page) {
		foreach($this->types as $type) {
			$classname = "Calendar_Event_Collection_Renderer_$type";

			$renderer = new $classname($api, $currentDay, $page);

			$this->registry[] = $renderer;
		}
	}

	public function renderAll() {
		foreach($this->registry as $renderer) {
			$renderer->render();
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set api
	 *
	 * @param $api
	 */
	private function setApi($api) {
		$this->api = $api;
	}

	/**
	 * Get api
	 *
	 * @return null
	 */
	private function getApi() {
		return $this->api;
	}

	/**
	 * Set current day
	 *
	 * @param $currentDay
	 */
	private function setCurrentDay($currentDay) {
		$this->currentDay = $currentDay;
	}

	/**
	 * Get current day
	 *
	 * @return int
	 */
	private function getCurrentDay() {
		return $this->currentDay;
	}

	/**
	 * Set page
	 *
	 * @param $page
	 */
	private function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return null
	 */
	private function getPage() {
		return $this->page;
	}
}
