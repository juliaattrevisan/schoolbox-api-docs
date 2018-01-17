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
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Calendar\Event\Collection;
use Calendar\Event\Type\EventInterface;

class Calendar_Api_Composite implements Calendar_Api_EventType_Interface
{
    /**
     * A list of all the api classes which produce Collections to combine
     *
     * @var Calendar_Api_EventType_Interface[] $api
     */
    private $api = [];

    /**
     * Get day events
     *
     * @param $startTimestamp
     * @param $endTimestamp
     * @internal param $currentDay
     * @return Collection
     */
    public function getEvents($startTimestamp, $endTimestamp)
    {
        $result = new Collection();

        foreach ($this->api as $currentApi) {
            $result->merge($currentApi->getEvents($startTimestamp, $endTimestamp));
        }

        $result->sort();

        return $result;
    }

    /**
     * Find a particular event matching an eventId from all registered sources
     *
     * @param $eventId
     * @return EventInterface
     */
    public function getEvent($eventId)
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
     * @param $api
     */
    public function add($api)
    {
        $this->api[] = $api;
    }
}
