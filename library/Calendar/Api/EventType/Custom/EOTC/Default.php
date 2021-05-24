<?php

/**
 * Calendar_Api_EventType_Event_Schoolbox_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\User;

class Calendar_Api_EventType_Custom_EOTC_Default extends Calendar_Api_EventType_Abstract
{

    /**
     * A schoobox event handler instance
     *
     * @var \EOTC\EventConnector
     */
    private $calendarHandler;


    /**
     * @inheritdoc
     */
    public function __construct(int $connectorId, User $user, User $viewer)
    {
        parent::__construct($connectorId, $user, $viewer);
        $this->calendarHandler = (new \EOTC\EventConnector())->setUser($user);
    }


    /**
     * Get day events from this data source
     *
     * @param int $startTimestamp
     * @param int $endTimestamp
     *
     * @return \Calendar\Event\Collection
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getEvents($startTimestamp, $endTimestamp)
    {
        $collection = $this->calendarHandler->getDayEvents($startTimestamp, $endTimestamp);

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
     * @param int $eventId
     *
     * @return \Calendar\Event\Collection
     */
    public function getEvent($eventId)
    {
        return $this->calendarHandler->getEvent($eventId);
    }
}
