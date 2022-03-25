<?php

use Schoolbox\Calendar\EventType;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\External\Event\Mapper;
use Schoolbox\User;

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

class Calendar_Api_EventType_SudsEvent_Schoolbox_Default extends Calendar_Api_EventType_Abstract
{

    /**
     * @var EventTypeMapper
     */
    private EventTypeMapper $eventTypeMapper;


    /**
     * @var \Schoolbox\External\Event\Mapper
     */
    private \Schoolbox\External\Event\Mapper $eventMapper;


    /**
     * @var \Schoolbox\External\Event\Campus\Mapper
     */
    private \Schoolbox\External\Event\Campus\Mapper $campusMapper;


    /**
     * @var \Schoolbox\External\Event\Roletype\Mapper
     */
    private \Schoolbox\External\Event\Roletype\Mapper $roletypeMapper;


    /**
     * @var \Schoolbox\Resource\Folder\Mapper
     */
    private \Schoolbox\Resource\Folder\Mapper $folderMapper;


    /**
     * @inheritdoc
     */
    public function __construct(
        int $connectorId,
        User $user,
        User $viewer,
        \Schoolbox\Calendar\EventTypeMapper $eventTypeMapper,
        \Schoolbox\External\Event\Mapper $eventMapper,
        \Schoolbox\External\Event\Campus\Mapper $campusMapper,
        \Schoolbox\External\Event\Roletype\Mapper $roletypeMapper,
        \Schoolbox\Resource\Folder\Mapper $folderMapper
    ) {
        parent::__construct($connectorId, $user, $viewer);

        $this->eventTypeMapper = $eventTypeMapper;
        $this->eventMapper = $eventMapper;
        $this->campusMapper = $campusMapper;
        $this->roletypeMapper = $roletypeMapper;
        $this->folderMapper = $folderMapper;
    }


    /**
     * Get day events from this data source
     *
     * @param int        $startTimestamp
     * @param int        $endTimestamp
     * @param array|null $allowedEventTypes
     * @return \Calendar\Event\Collection
     * @throws PDOException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
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

        /* The list of events in which $this->getUser() is involved
         */
        $events = (new \Schoolbox\External\Event\EventUsers(
            $this->eventMapper,
            $this->campusMapper,
            $this->roletypeMapper
        ))->findForUser($this->getUser(), $startTimestamp, $endTimestamp);

        /** @var \Schoolbox\External\Event\Model $event */
        foreach ($events as $event) {
            $instance = new \Calendar\Event\Type\SudsTimetable\Event();
            $instance->setConnectorId($this->getConnectorId());
            $instance->setTitle($event->getTitle());
            $instance->setDetail($event->getDetail());
            $instance->setLocation($event->getLocation());
            $instance->setCode($event->getCode());
            $instance->setDay($event->getDay());
            $instance->setPeriod($event->getPeriod());
            $instance->setStartTime($event->getStartAt());
            $instance->setEndTime($event->getFinishAt());
            $instance->setAllDay(false);
            $instance->setColour($eventColor);
            $instance->setType($eventType);
            $instance->setFolder($event->getFolder());

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
