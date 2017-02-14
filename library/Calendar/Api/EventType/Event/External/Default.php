<?php
/**
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */

use Schoolbox\External\Connector\Synergetic\Base\ExternalEvents;
use Calendar\Event\Collection;
use Calendar\Event\Type\External\Event;

//class Calendar_Api_External_Event_Default extends Calendar_Api_EventType_Abstract {
class Calendar_Api_EventType_Event_External_Default extends Calendar_Api_EventType_Abstract {

    /**
     * An external event handler instance
     *
     * @var Calendar
     */
    private $externalEvents;

    /**
     * Constructor
     *
     * @param $user
     * @param $config
     */
    public function __construct($user, $config) {
        $this->setUser($user);
        $this->externalEvents = new ExternalEvents();
    }

    /**
     * Get day events from this data source
     *
     * @param int $startTimestamp
     * @param int $endTimestamp
     *
     * @return \Calendar\Event\Collection
     */
    public function getEvents($startTimestamp, $endTimestamp) {

        $stack            = new Collection();
        $externalEvents = $this->externalEvents->getExternalEvents();

        // Figure out event type
        try {
            /** @var Calendar_Api_Config_Record $config */
            $config        = $this->loadConnectorConfiguration();
            $decodedConfig = $config->decodeConfig();
            $eventType     = $decodedConfig->getLuEventType();
        } catch (\Schoolbox\Mapper\Exception $e) {
            $eventType = 1;
        }

        foreach ($externalEvents as $externalEvent) {
            $event = new Event();
            $event->setEventId(rand(0, 1000)); //Todo
            $event->setEventType($eventType);
            $event->setTitle($externalEvent['title']);
            $event->setDetail($externalEvent['description']);
            $event->setStartTime(new Alaress_Date_Format($externalEvent['start'], 'Y-m-d H:i:s'));
            $event->setEndTime(new Alaress_Date_Format($externalEvent['finish'], 'Y-m-d H:i:s'));
            $event->setEventType('external');
            $event->setAllday(false);
            $event->setAuthorId($externalEvent['owner']);
            $event->setLocation($externalEvent['location']);
            $stack->add($event);
        }
        return $stack;

    }

    /**
     * Find a particular event from this source
     * This implementation of getEvent is actually used
     * (by /www/calendar/ajax/create.php, to display a new event)
     *
     * @param $eventId
     * @return \Calendar\Event\Collection
     */
    public function getEvent($eventId) {
        return '';
    }

    /**
     * Helper method for loading this event type's calendar connector config.
     *
     * @return Calendar_Api_Config_Record|object
     */
    public function loadConnectorConfiguration()
    {
        return Calendar_Api_Config_Mapper::getInstance()->findById(
            $this->getConnectorId()
        );
    }
}
