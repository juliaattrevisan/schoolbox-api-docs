<?php

/**
 * Calendar_Api_EventType_Event_Schoolbox_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Api_EventType_Custom_EOTC_Default extends Calendar_Api_EventType_Abstract
{

    /**
     * A schoobox event handler instance
     *
     * @var \EOTC\EventConnector
     */
    private $calendarHandler;

    /**
     * Constructor
     *
     * @param $user
     * @param $config
     */
    public function __construct($user, $config)
    {
        parent::__construct($user);
        $connector = new \EOTC\EventConnector();
        $connector->setUser($user);
        $this->setCalendarHandler($connector);
    }

    /**
     * Get day events from this data source
     *
     * @param int $startTimestamp
     * @param int $endTimestamp
     *
     * @return \Calendar\Event\Collection
     */
    public function getEvents($startTimestamp, $endTimestamp)
    {
        $collection = $this->getCalendarHandler()->getDayEvents($startTimestamp, $endTimestamp);

        /* Give the calendar_connector id so this event knows where it came from
         * This allows you to create an event detail screen in Mobile
         */
        foreach ($collection as $current) {
            $current->setConnectorId($this->getConnectorId());
        }

        return $collection;
    }

    /**
     * Find a particular event from this source
     *
     * @param $eventId
     *
     * @return \Calendar\Event\Collection
     */
    public function getEvent($eventId)
    {
        $collection = $this->getCalendarHandler()->getEvent($eventId);

        return $collection;
    }

    /* Getters/Setters
     */

    /**
     * Set calendar handler
     *
     * @param \EOTC\EventConnector $calendarHandler
     */
    public function setCalendarHandler($calendarHandler)
    {
        $this->calendarHandler = $calendarHandler;
    }

    /**
     * Get calendar handler
     *
     * @return \EOTC\EventConnector
     */
    public function getCalendarHandler()
    {
        return $this->calendarHandler;
    }
}
