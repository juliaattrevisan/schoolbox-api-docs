<?php

use Calendar\Event\Type\EventInterface;
use Schoolbox\Collection;

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
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
interface Calendar_Api_EventType_Interface
{

    /**
     * @param int $startTimestamp
     * @param int $endTimestamp
     * @return Collection
     */
    public function getEvents($startTimestamp, $endTimestamp);


    /**
     * @param $eventId
     * @return EventInterface
     */
    public function getEvent($eventId);
}
