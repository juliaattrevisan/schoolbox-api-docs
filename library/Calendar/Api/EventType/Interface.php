<?php

use Calendar\Event\Type\EventInterface;
use Schoolbox\GenericCollection;

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
     * @return GenericCollection<EventInterface>
     */
    public function getEvents(
        int $startTimestamp,
        int $endTimestamp,
        ?array $allowedEventTypes = null,
    ): GenericCollection;


    /**
     * Get a single event
     *
     * @param int $eventId
     * @return GenericCollection<EventInterface> A single event in a collection
     */
    public function getEvent($eventId): GenericCollection;
}
