<?php
/**
 * Calendar_Api_Submission_Vcal_Default
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_Submission_Vcal_Default implements Calendar_Api_Interface {

	/**
	 * Get day events from this data source
	 *
	 * @param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getEvents($currentDay) {
		$collection = new Calendar_Event_Collection();

		/* Fill in your query result set here and populate $collection
		 */

		return $collection;
	}
}
