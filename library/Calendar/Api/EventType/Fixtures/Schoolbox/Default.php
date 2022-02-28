<?php

use Schoolbox\User;

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

class Calendar_Api_EventType_Fixtures_Schoolbox_Default extends Calendar_Api_EventType_Abstract
{

    /**
     * Get day events from this data source
     *
     * @param int        $startTimestamp
     * @param int        $endTimestamp
     * @param array|null $allowedEventTypes
     * @return \Calendar\Event\Collection
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): \Calendar\Event\Collection
    {
        $events = \Schoolbox\Fixtures\Matchup\Mapper::getInstance()->findInvolvedMatchups($this->getUser());

        foreach ($events as $event) {
            print_r($event);
        }

        return new \Calendar\Event\Collection();
    }


    /**
     * Find a particular event from this source
     * This implementation of getEvent is actually used
     * (by /www/calendar/ajax/create.php, to display a new event)
     *
     * @param int $eventId
     * @return \Calendar\Event\Collection
     */
    public function getEvent($eventId)
    {
        return new \Calendar\Event\Collection();
    }
}
