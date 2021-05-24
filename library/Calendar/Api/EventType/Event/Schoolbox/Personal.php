<?php

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

class Calendar_Api_EventType_Event_Schoolbox_Personal extends Calendar_Api_EventType_Event_Schoolbox_Default
{

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
            array('personal' => $this->getUser()->getId())
        );
    }
}
