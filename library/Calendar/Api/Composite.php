<?php
/**
 * Calendar_Api_Composite
 *
 * Use Composite pattern to treat multiple API instances as one.
 *
 * This is so you can turn sources on/off and have school-specific
 * implementations for each data source
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_Composite implements Calendar_Api_Interface {

	/**
	 * A list of all the api classes which produce Collections to combine
	 *
	 * @var array
	 */
	private $api = array();

	/**
	 * Get day events
	 *
	 * @param $currentDay
	 * @return Calendar_Event_Collection
	 */
	public function getEvents($currentDay) {
		$result = new Calendar_Event_Collection();

		foreach($this->getApi() as $currentApi) {
			$result->merge($currentApi->getEvents($currentDay));
		}

		return $result;
	}

	/**
	 * Get student birthdays
	 *
	 * @param $currentDay
	 * @return Calendar_Event_Collection
	 */
	public function getStudentBirthdays($currentDay) {
		$result = new Calendar_Event_Collection();

		foreach($this->getApi() as $currentApi) {
			$result->merge($currentApi->getStudentBirthdays($currentDay));
		}

		return $result;
	}

	/**
	 * Get submission events
	 *
	 * @param $currentDay
	 * @return Calendar_Event_Collection
	 */
	public function getSubmissionEvents($currentDay) {
		$result = new Calendar_Event_Collection();

		foreach($this->getApi() as $currentApi) {
			$result->merge($currentApi->getSubmissionEvents($currentDay));
		}

		return $result;
	}

	/**
	 * Add an api to the stack
	 *
	 * @param $api
	 */
	public function add($api) {
		$this->api[] = $api;
	}

	/**
	 * Get the number of apis currently being used
	 *
	 * @return int
	 */
	public function getCount() {
		return count($this->getApi());
	}

	/* Getters/Setters
	 */

	/**
	 * Get api
	 *
	 * @return array
	 */
	public function getApi() {
		return $this->api;
	}
}
