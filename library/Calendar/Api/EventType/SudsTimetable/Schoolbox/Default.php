<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Calendar\Event\Type\EventInterface;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\External\Connector\AttendanceFactory;
use Schoolbox\External\Event\Overrides\Mapper;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as Folder;

class Calendar_Api_EventType_SudsTimetable_Schoolbox_Default extends Calendar_Api_EventType_Abstract
{
    /**
     * @var GenericCollection|null
     */
    private ?GenericCollection $folderCodes;


    /**
     * @var \Schoolbox\IConfigLoader
     */
    private \Schoolbox\IConfigLoader $configLoader;


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
     * @var Timetable_ColourMapper
     */
    private \Timetable_ColourMapper $timetableColourMapper;


    /**
     * @var \Schoolbox\External\Profile\Staff\Mapper
     */
    private \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper;

    /**
     * @var Access
     */
    private Access $access;

    /**
     * @inheritdoc
     */
    public function __construct(
        int $connectorId,
        User $user,
        User $viewer,
        ?GenericCollection $folderCodes,
        \Schoolbox\IConfigLoader $configLoader,
        \Schoolbox\Calendar\EventTypeMapper $eventTypeMapper,
        \Schoolbox\External\ClassEntryMapper $classEntryMapper,
        \Schoolbox\External\ClassInvolvementMapper $classInvolvementMapper,
        \Schoolbox\External\Event\Mapper $eventMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overrideMapper,
        \Timetable_ColourMapper $timetableColourMapper,
        \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper,
        Access $access,
    ) {
        parent::__construct($connectorId, $user, $viewer);

        $this->folderCodes = $folderCodes;
        $this->configLoader = $configLoader;
        $this->eventTypeMapper = $eventTypeMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->eventMapper = $eventMapper;
        $this->overrideMapper = $overrideMapper;
        $this->timetableColourMapper = $timetableColourMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->access = $access;
    }


    /**
     * Get day events from this data source
     *
     * @param int        $startTimestamp
     * @param int        $endTimestamp
     * @param array|null $allowedEventTypes
     * @return GenericCollection<EventInterface>
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): GenericCollection
    {
        $stack = new GenericCollection(EventInterface::class);

        /* If the viewee's Hide Timetable is set, hide it from everyone
         */
        if ($this->getUser()->getHideTimetable()) {
            return $stack;
        }

        // Find the event type from this connector's configuration:
        // if there is no configuration, use the first meaningful event type
        try {
            $configuration     = $this->loadConnectorConfiguration();
            $eventTypeSettings = (new \Schoolbox\External\Event\EventTypeGenerator($this->eventTypeMapper))
                ->findEventType($configuration, $allowedEventTypes);
            /* Event type is not allowed or not found a null will be returned, throw error for fall back value
             */
            if (null === $eventTypeSettings) {
                throw new \Schoolbox\Mapper\Exception('Event type settings not found.');
            }
            $eventType = $eventTypeSettings['type'];
        } catch (\Schoolbox\Mapper\Exception $e) {
            $eventType = $this->eventTypeMapper->findFirstMeaningfulId(null);
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

        /* Decide if the current SUDS connector supports Attendance Link generation
         * If it does, use it repeatedly to create said links
         * Otherwise it remains null and will do nothing
         */
        $attendanceLinkGenerator = (new AttendanceFactory($this->configLoader))->produce();

        /** @var \Schoolbox\External\Event\Model $timetableEvent */
        foreach ($timetableEvents as $timetableEvent) {
            $attendanceLink = $attendanceLinkGenerator?->generate($timetableEvent, $this->getUser()) ?? '';

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
            $instance->setType($eventType);
            //Only when the user has the right permission will have link to homepage
            if (
                $timetableEvent->getFolder() instanceof Folder &&
                $this->access->getFolderAccess(
                    $this->getViewer(),
                    $timetableEvent->getFolder()->getId()
                ) >= UserAccess::ACCESS_READ
            ) {
                $instance->setFolder($timetableEvent->getFolder());
            } else {
                $instance->setFolder(null);
            }
            $instance->setStaff($timetableEvent->getStaff());
            $instance->setAttendanceUrl($attendanceLink);

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
     * @return GenericCollection<EventInterface>
     */
    public function getEvent($eventId): GenericCollection
    {
        /* TODO I don't think this is used anymore...
         */
        return new GenericCollection(EventInterface::class);
    }
}
