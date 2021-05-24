<?php

use Schoolbox\User;

/**
 * Calendar_Api_EventType_Event_Schoolbox_Personal
 *
 * Like Calendar_Api_EventType_Event_Schoolbox_Default, but only returns events
 * that are personal.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class Calendar_Api_EventType_Event_Schoolbox_Type extends Calendar_Api_EventType_Event_Schoolbox_Default
{

    /**
     * Event Type to fetch.
     *
     * @var null|int
     */
    private $eventTypeId;


    /**
     * @param int      $connectorId
     * @param User     $user
     * @param User     $viewer
     * @param int|null $eventTypeId
     */
    public function __construct(int $connectorId, User $user, User $viewer, int $eventTypeId = null)
    {
        parent::__construct($connectorId, $user, $viewer);
        $this->eventTypeId = $eventTypeId;
    }


    /**
     * Overridden to get personal events only.
     *
     * User filtering is done in query - this may change, as admins may want to
     * fetch personal events from other calendars.
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
            array('type' => $this->eventTypeId)
        );
    }
}
