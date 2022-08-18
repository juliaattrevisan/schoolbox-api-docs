<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\External\Event\Overrides\Mapper;
use Schoolbox\GenericCollection;
use Schoolbox\User;

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
     * @var Timetable_ColourMapper
     */
    private \Timetable_ColourMapper $timetableColourMapper;


    /**
     * @var \Schoolbox\External\Profile\Staff\Mapper
     */
    private \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper;


    /**
     * @var GenericCollection|null
     */
    private ?GenericCollection $folderCodes;


    /**
     * @inheritdoc
     */
    public function __construct(
        int $connectorId,
        User $user,
        User $viewer,
        ?GenericCollection $folderCodes,
        \Schoolbox\Calendar\EventTypeMapper $eventTypeMapper,
        \Schoolbox\External\ClassEntryMapper $classEntryMapper,
        \Schoolbox\External\ClassInvolvementMapper $classInvolvementMapper,
        \Schoolbox\External\Event\Mapper $eventMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overrideMapper,
        \Schoolbox\UserMapper $userMapper,
        \Timetable_ColourMapper $timetableColourMapper,
        \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper
    ) {
        parent::__construct($connectorId, $user, $viewer);

        $this->folderCodes = $folderCodes;
        $this->eventTypeMapper = $eventTypeMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->eventMapper = $eventMapper;
        $this->overrideMapper = $overrideMapper;
        $this->userMapper = $userMapper;
        $this->timetableColourMapper = $timetableColourMapper;
        $this->staffProfileMapper = $staffProfileMapper;
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
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \Schoolbox\Mapper\Exception\NotFound
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): \Calendar\Event\Collection
    {
        $stack = new \Calendar\Event\Collection();

        /* If the viewee's Hide Timetable is set, hide it from everyone
         */
        if ($this->getUser()->getHideTimetable()) {
            return $stack;
        }

        try {
            $eventTypeSettings = (new \Schoolbox\External\Event\EventTypeGenerator($this->eventTypeMapper))
                ->findEventType($this->loadConnectorConfiguration(), $allowedEventTypes);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $eventTypeSettings = null;
        }

        /* No event type for the ($config, $allowedEventTypes) combination
         * or something went wrong with the event type detection
         */
        if (null === $eventTypeSettings) {
            return $stack;
        }

        /* The list of events in which $this->getUser() is involved
         * They're involved because they're part of the usual class list
         * or they've been mutated into said list via overrides
         */
        $timetableEvents = (new \Schoolbox\External\Event\TimetableUsers(
            $this->classEntryMapper,
            $this->classInvolvementMapper,
            $this->eventMapper,
            $this->overrideMapper,
            $this->userMapper,
            $this->staffProfileMapper
        ))->findForUser(
            $this->getUser(),
            $startTimestamp,
            $endTimestamp,
            $this->folderCodes ?? null
        );

        /* Generate a unique association between event codes and the
         * available sambell pastel colours, overriding the colours entirely
         */
        $timetableColorManager = new Timetable_ColourManager($this->timetableColourMapper->findAll()->reindex());

        /** @var \Schoolbox\External\Event\Model $timetableEvent */
        foreach ($timetableEvents as $timetableEvent) {
            $instance = new \Calendar\Event\Type\SudsTimetable\Event();
            $instance->setEventId($timetableEvent->getId());
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
            $instance->setColour($timetableColorManager->getColour($timetableEvent->getCode()));
            $instance->setType($eventTypeSettings['type']);
            $instance->setFolder($timetableEvent->getFolder());
            $instance->setStaff($timetableEvent->getStaff());

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
