<?php

use Schoolbox\User;

/**
 * Calendar_Api_EventType_Event_Schoolbox_Campus
 *
 * Like Calendar_Api_EventType_Event_Schoolbox_Default, but only returns events
 * for a given campus.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class Calendar_Api_EventType_Event_Schoolbox_Campus extends Calendar_Api_EventType_Event_Schoolbox_Default
{

    /**
     * The campus to get events for. If null, returns events for all campuses.
     *
     * @var null|int
     */
    private $campus_id;


    /**
     * @param int      $connectorId
     * @param User     $user
     * @param User     $viewer
     * @param null|int $campusId
     */
    public function __construct(int $connectorId, User $user, User $viewer, int $campusId = null)
    {
        parent::__construct($connectorId, $user, $viewer);
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
    protected function getEventsFromCalendarHandler($startTimestamp, $endTimestamp)
    {
        return $this->getCalendarHandler()->getDayEvents(
            $this->getUser(),
            $this->getViewer(),
            $startTimestamp,
            $endTimestamp,
            ['campus' => $this->campus_id]
        );
    }
}
