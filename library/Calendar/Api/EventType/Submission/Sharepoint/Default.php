<?php
/**
 * Calendar_Api_EventType_Submission_Sharepoint_Default
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_EventType_Submission_Sharepoint_Default implements Calendar_Api_EventType_Interface {

	/**
	 * Constructor
	 *
	 * @param $config
	 */
	public function __construct($config) {
	}

	/**
	 * Get day events from this data source
	 *
	 * @param $startTimestamp
	 * @param $endTimestamp
	 * @internal param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getEvents($startTimestamp, $endTimestamp) {
		$collection = new Calendar_Event_Collection();

		/* Fill in your query result set here and populate $collection
		 */

		return $collection;
	}
}
