<?php

/**
 * Calendar_Api_EventType_Interface
 *
 * Each method must return an instance of \Calendar\Event\Collection
 * containing Calender_Event_Type subclasses in their stacks.
 *
 * When adding a new type of event collection e.g. getDayEvents(),
 * add it here first.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
interface Calendar_Api_EventType_Interface
{

    /**
     * Get a set of event instances
     *
     * @param int        $startTimestamp
     * @param int        $endTimestamp
     * @param array|null $allowedEventTypes
     * @return Calendar\Event\Collection
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): \Calendar\Event\Collection;


    /**
     * Get a single event
     *
     * @param int $eventId
     * @todo What does this return?
     */
    public function getEvent($eventId);
}
