<?php
/**
 * Calendar_Api_EventType_Event_Sharepoint_Default
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 */

use \Calendar\Event\Collection;

class Calendar_Api_EventType_Event_Sharepoint_Default extends Calendar_Api_EventType_Abstract {

	/**
	 * Constructor
	 *
	 * @param $user
	 * @param $config
	 */
	public function __construct($user, $config) {
	}

	/**
	 * Get day events from this data source
	 *
	 * @param $startTimestamp
	 * @param $endTimestamp
	 * @internal param $currentDay
	 *
	 * @return Collection
	 */
	public function getEvents($startTimestamp, $endTimestamp) {
		$collection = new Collection();

		/* Fill in your query result set here and populate $collection
		 */
		foreach ($collection as $current) {
			$current->setConnectorId($this->getConnectorId());
		}

		return $collection;
	}

	/**
	 * Find a particular event from this source
	 *
	 * @param $eventId
	 * @return Collection
	 */
	public function getEvent($eventId) {
		$collection = new Collection();

		/* Fill in your query result set here and populate $collection
		 */

		return $collection;
	}
}
