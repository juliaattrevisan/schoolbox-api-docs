<?php

use Schoolbox\Calendar\EventType;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\User;

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

class Calendar_Api_EventType_Fixtures_Schoolbox_Default extends Calendar_Api_EventType_Abstract
{

    /**
     * @var EventTypeMapper
     */
    private EventTypeMapper $eventTypeMapper;


    /**
     * @inheritdoc
     */
    public function __construct(int $connectorId, User $user, User $viewer, \Schoolbox\Calendar\EventTypeMapper $eventTypeMapper)
    {
        parent::__construct($connectorId, $user, $viewer);

        $this->eventTypeMapper   = $eventTypeMapper;
    }


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
        $stack = new \Calendar\Event\Collection();

        // Figure out event type
        try {
            /** @var Calendar_Api_Config_Record $config */
            $config        = $this->loadConnectorConfiguration();
            $decodedConfig = $config->decodeConfig();
            $eventType     = $decodedConfig->getLuEventType();
        } catch (\Schoolbox\Mapper\Exception $e) {
            /* Something went awry with loadConnectorConfiguration()
             * Can't determine what event type this would be
             */
            $eventType = null;
        }

        /* Ensure $eventType is valid, finding first available one if it is invalid.
         * If you can't find one, we can't assign anything to the events from this subscription
         * so just return the empty stack; there's no way we could categorise those events
         *
         * getLuEventType() can return a deleted event type identifier.
         */
        $eventType = $this->eventTypeMapper->findFirstMeaningfulId($eventType);

        /* Do a detection on $allowedEventTypes, if provided and meaningful
         */
        if (null !== $allowedEventTypes && \count($allowedEventTypes)) {
            if (!\in_array((int)$eventType, $allowedEventTypes, true)) {
                return $stack;
            }
        }

        $eventColor = null;

        try {
            /** @var EventType $eventTypeObject */
            $eventTypeObject = $this->eventTypeMapper->findById($eventType);
            $eventColor      = $eventTypeObject->getColor();
        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop
        }

        /* Skim the list
         */
        $matchups = \Schoolbox\Fixtures\Matchup\Mapper::getInstance()->findInvolvedMatchups($this->getUser());

        /** @var \Schoolbox\Fixtures\Matchup\Model $matchup */
        foreach ($matchups as $matchup) {
            $instance = new \Calendar\Event\Type\Fixtures\Event();

            $instance->setConnectorId($this->getConnectorId());
            $instance->setTitle($matchup->getName());
            $instance->setStartTime($matchup->getStartAt());
            $instance->setAllDay(true);
            $instance->setLocation($matchup->getVenue()->getName());
            $instance->setColour($eventColor);
            $instance->setType($eventType);

            $stack->add($instance);
        }

        return $stack;
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
        /* TODO I don't think this is used anymore...
         */
        return new \Calendar\Event\Collection();
    }
}
