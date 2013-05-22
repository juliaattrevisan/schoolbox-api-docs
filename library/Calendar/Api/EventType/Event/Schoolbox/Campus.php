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
	 * Overridden to filter out events not for this group.
	 * TODO stop being lazy - implement proper filtering at the query level
	 *
	 * @param int $startTimestamp
	 * @param int $endTimestamp
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getEvents($startTimestamp, $endTimestamp) {
		$event_list = parent::getEvents($startTimestamp, $endTimestamp);
		$new_list   = array();
		foreach ($event_list as $event) {
			if ($event->getAccess() == Calendar::ACCESS_CAMPUS) {
				if (in_array($this->getCampusId(), explode(',', $event->getCampusList()))) {
					$new_list[] = $event;
				}
			}
		}
		$event_list->setStack($new_list);
		return $event_list;
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
