<?php

use Schoolbox\User;

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
	 * The campus to get events for. If null, returns events for all campuses.
	 *
	 * @var null|int
	 */
	private $campus_id;


    /**
     * @param int $connectorId
     * @param User     $user
     * @param null|int $campusId
     */
	public function __construct(int $connectorId, User $user, int $campusId = null)
    {
        parent::__construct($connectorId, $user);
        $this->campus_id = $campusId;
    }


    /**
	 * Overridden to get events only for this campus.
	 *
	 * @param int $startTimestamp
	 * @param int $endTimestamp
	 *
	 * @return \Calendar\Event\Collection
	 */
	protected function getEventsFromCalendarHandler($startTimestamp, $endTimestamp) {
		return $this->getCalendarHandler()->getDayEvents(
			$this->getUser(),
			$startTimestamp,
			$endTimestamp,
			array('campus' => $this->campus_id)
		);
	}
}
