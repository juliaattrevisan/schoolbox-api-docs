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

use Calendar\Event\SortStrategy;
use Calendar\Event\Type\EventInterface;
use Schoolbox\GenericCollection;

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
     * @return GenericCollection<EventInterface>
     * @internal param $currentDay
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): GenericCollection
    {
        $result = new GenericCollection(EventInterface::class);

        foreach ($this->api as $currentApi) {
            $result = $result->merge($currentApi->getEvents($startTimestamp, $endTimestamp, $allowedEventTypes));
        }

        return $result->sortByCallback(new SortStrategy());
    }

    /**
     * Find a particular event matching an eventId from all registered sources
     *
     * @param int $eventId
     * @return GenericCollection<EventInterface>
     */
    public function getEvent($eventId): GenericCollection
    {
        $result = new GenericCollection(EventInterface::class);

        foreach ($this->api as $currentApi) {
            $result = $result->merge($currentApi->getEvent($eventId));
        }

        return $result->first();
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
