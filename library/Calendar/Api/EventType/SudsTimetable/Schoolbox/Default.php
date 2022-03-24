<?php

use Schoolbox\Calendar\EventType;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\External\Event\Overrides\Mapper;
use Schoolbox\User;

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

class Calendar_Api_EventType_SudsTimetable_Schoolbox_Default extends Calendar_Api_EventType_Abstract
{

    /**
     * @var EventTypeMapper
     */
    private EventTypeMapper $eventTypeMapper;


    /**
     * @var \Schoolbox\External\ClassEntryMapper
     */
    private \Schoolbox\External\ClassEntryMapper $classEntryMapper;


    /**
     * @var \Schoolbox\External\ClassInvolvementMapper
     */
    private \Schoolbox\External\ClassInvolvementMapper $classInvolvementMapper;


    /**
     * @var \Schoolbox\External\Event\Mapper
     */
    private \Schoolbox\External\Event\Mapper $eventMapper;


    /**
     * @var Mapper
     */
    private \Schoolbox\External\Event\Overrides\Mapper $overrideMapper;


    /**
     * @var \Schoolbox\UserMapper
     */
    private \Schoolbox\UserMapper $userMapper;


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
        \Schoolbox\External\ClassEntryMapper $classEntryMapper,
        \Schoolbox\External\ClassInvolvementMapper $classInvolvementMapper,
        \Schoolbox\External\Event\Mapper $eventMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overrideMapper,
        \Schoolbox\UserMapper $userMapper,
        \Schoolbox\Resource\Folder\Mapper $folderMapper
    ) {
        parent::__construct($connectorId, $user, $viewer);

        $this->eventTypeMapper = $eventTypeMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->eventMapper = $eventMapper;
        $this->overrideMapper = $overrideMapper;
        $this->userMapper = $userMapper;
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

        $generator = new \Schoolbox\External\Event\Overrides\InvolvedUsers(
            $this->classEntryMapper,
            $this->classInvolvementMapper,
            $this->eventMapper,
            $this->overrideMapper,
            $this->userMapper
        );

        /* The list of events in which $this->getUser() is involved
         * They're involved because they're part of the usual class list
         * or they've been mutated into said list via overrides
         */
        $timetableEvents = $generator->generateForUser(
            $this->getUser(),
            $startTimestamp,
            $endTimestamp
        );

        /** @var \Schoolbox\External\Event\Model $timetableEvent */
        foreach ($timetableEvents as $timetableEvent) {
            $instance = new \Calendar\Event\Type\SudsTimetable\Event();
            $instance->setConnectorId($this->getConnectorId());
            $instance->setTitle($timetableEvent->getTitle());
            $instance->setDetail($timetableEvent->getDetail());
            $instance->setLocation($timetableEvent->getLocation());
            $instance->setCode($timetableEvent->getCode());
            $instance->setDay($timetableEvent->getDay());
            $instance->setPeriod($timetableEvent->getPeriod());
            $instance->setStartTime($timetableEvent->getStartAt());
            $instance->setEndTime($timetableEvent->getFinishAt());
            $instance->setAllDay(false);
            $instance->setColour($eventColor);
            $instance->setType($eventType);
            $instance->setFolder($timetableEvent->getFolder());

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
