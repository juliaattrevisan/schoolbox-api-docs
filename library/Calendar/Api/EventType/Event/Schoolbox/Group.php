<?php

use Schoolbox\User;

/**
 * Like Calendar_Api_EventType_Event_Schoolbox_Default, but only returns events
 * for a given group.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Calendar_Api_EventType_Event_Schoolbox_Group extends Calendar_Api_EventType_Event_Schoolbox_Default {

    /**
     * The group to get events for. Does not filter by group if NULL
     *
     * @var null|int
     */
    private $group_id;


    /**
     * @param int      $connectorId
     * @param User     $user
     * @param int|null $groupId
     */
    public function __construct(int $connectorId, User $user, int $groupId = null)
    {
        parent::__construct($connectorId, $user);
        $this->group_id = $groupId;
    }


    /**
	 * Overridden to get events only for this group.
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
            ['group' => $this->group_id]
        );
	}
}
