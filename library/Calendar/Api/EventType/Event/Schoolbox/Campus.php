<?php
/**
 * Calendar_Api_EventType_Event_Schoolbox_Campus
 *
 * Like Calendar_Api_EventType_Event_Schoolbox_Default, but only returns events
 * for a given campus.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Calendar_Api_EventType_Event_Schoolbox_Campus extends Calendar_Api_EventType_Event_Schoolbox_Default {

	/**
	 * The campus to get events for
	 *
	 * @var int
	 */
	protected $campus_id;

	/**
	 * Overridden to get events only for this campus.
	 *
	 * @param int $startTimestamp
	 * @param int $endTimestamp
	 *
	 * @return Calendar_Event_Collection
	 */
	protected function getEventsFromCalendarHandler($startTimestamp, $endTimestamp) {
		return $this->getCalendarHandler()->getDayEvents(
			$startTimestamp,
			$endTimestamp,
			array('campus' => $this->getCampusId())
		);
	}

	/**
	 * @param  int $campus_id
	 *
	 * @return $this
	 */
	public function setCampusId($campus_id) {
		$this->campus_id = $campus_id;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getCampusId() {
		return $this->campus_id;
	}
}
