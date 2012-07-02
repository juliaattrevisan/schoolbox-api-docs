<?php
/**
 * Calendar_Bridge_Stk
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Bridge_Stk implements Calendar_Bridge_Interface {
	private $api = null;

	public function __construct() {

		$registry = Cfg::get('calendar_types'); // array('Iport', 'Schoolbox');

		/* Use this to change API strategy
		 */
		foreach($registry as $current) {
			$classname = 'Calendar_Bridge_' . $current . '_' . ucwords(Cfg::get('site_domain'));

			if (!class_exists($classname)) {
//				$classname = default classname; // Calendar_Bridge_Schoolbox
			}

			$this->api[] = new $classname();
		}
	}

	public function getAllEvents() {
		foreach($this->api as $current) {
			$allEvents[] = $current->getAllEvents();
		}

		/* Extra processing
		 */
		$result = $allEvents;

		return $result;
	}

	public function getAllEventsForCategory() {
		$allEvents = $this->api->getAllEventsForCategory();

		/* Extra processing
		 */
		$result = $allEvents;

		return $result;
	}
}
