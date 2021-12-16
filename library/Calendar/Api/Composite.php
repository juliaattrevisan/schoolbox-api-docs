<?php

/**
 * Calendar_Api_Composite
 *
 * Use Composite pattern to treat multiple API instances as one.
 *
 * This is so you can turn sources on/off and have school-specific
 * implementations for each data source
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Calendar\Event\Collection;
use Calendar\Event\Type\EventInterface;

class Calendar_Api_Composite implements Calendar_Api_EventType_Interface
{
    /**
     * A list of all the api classes which produce Collections to combine
     *
     * @var Calendar_Api_EventType_Interface[]
     */
    private array $api = [];


    /**
     * Get day events
     *
     * @param int        $startTimestamp
     * @param int        $endTimestamp
     * @param array|null $allowedEventTypes
     * @return Collection
     * @internal param $currentDay
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): Collection
    {
        $result = new Collection();

        foreach ($this->api as $currentApi) {
            $result->merge($currentApi->getEvents($startTimestamp, $endTimestamp, $allowedEventTypes));
        }

        $result->sort();

        return $result;
    }

    /**
     * Find a particular event matching an eventId from all registered sources
     *
     * @param int $eventId
     * @return EventInterface
     */
    public function getEvent($eventId): EventInterface
    {
        $result = new Collection();

        foreach ($this->api as $currentApi) {
            $result->merge($currentApi->getEvent($eventId));
        }

        return $result->getFirst();
    }

    /**
     * Add an api to the stack
     *
     * @param Calendar_Api_EventType_Interface $api
     */
    public function add(Calendar_Api_EventType_Interface $api): void
    {
        $this->api[] = $api;
    }
}
