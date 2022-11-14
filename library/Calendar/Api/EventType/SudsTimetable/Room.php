<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

/**
 * Class Room
 *
 * Finds all the rooms currently in use between the prescribed timestamps.
 * This is used to populate the Resource Booking calendar to indicate when
 * a Room is currently in use so people don't try to book it at the same time.
 */
class Room implements Calendar_Api_EventType_Interface
{
    /**
     * Constructor.
     *
     * @param \Schoolbox\External\Event\Mapper $mapper
     */
    public function __construct(private \Schoolbox\External\Event\Mapper $mapper)
    {
    }


    /**
     * Find the usages of the $roomCodes between the timestamps given,
     * $startTimestamp and $endTimestamp
     *
     * $roomCodes is a tuple (room code, resource booking item instance id)
     *
     * If you don't provide any $roomCodes, it won't returns an empty set.
     *
     * @param int        $startTimestamp
     * @param int        $endTimestamp
     * @param array|null $allowedEventTypes
     * @return \Calendar\Event\Collection
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): \Calendar\Event\Collection
    {
        // TODO: Implement getEvents() method.
    }


    public function getEvent($eventId)
    {
        // TODO: Implement getEvent() method.
        // TODO: I don't think this is even used anymore
    }
}