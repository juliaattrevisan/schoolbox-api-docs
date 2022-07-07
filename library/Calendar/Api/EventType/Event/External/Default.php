<?php

/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Calendar\Event\Collection;
use Calendar\Event\Type\External\Event;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Calendar\EventType;
use Schoolbox\External\Connector\IExternalEvents;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Calendar_Api_EventType_Event_External_Default extends Calendar_Api_EventType_Abstract
{
    /**
     * A string to indicate the source from which this event emerged
     *
     * @var string
     */
    private const EXTERNAL_EVENT_TYPE = 'external';

    /**
     * An external event handler instance
     *
     * @var IExternalEvents
     */
    private $externalEvents;

    /**
     * @var EventTypeMapper
     */
    protected $eventTypeMapper;

    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * Constructor
     *
     * @param int             $connectorId
     * @param User            $user
     * @param User            $viewer
     * @param IExternalEvents $externalEvents
     */
    public function __construct(int $connectorId, User $user, User $viewer, $externalEvents)
    {
        parent::__construct($connectorId, $user, $viewer);
        $this->externalEvents  = $externalEvents;
        $this->eventTypeMapper = EventTypeMapper::getInstance();
        $this->userMapper      = UserMapper::getInstance();
    }


    /**
     * Get events from this data source
     *
     * @param int $startTimestamp
     * @param int $endTimestamp
     * @return Collection
     * @throws InvalidArgumentException
     * @throws MapperException
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): Collection
    {
        $stack = new Collection();

        // Figure out default event type
        try {
            /** @var Calendar_Api_Config_Record $config */
            $config           = $this->loadConnectorConfiguration();
            $decodedConfig    = $config->decodeConfig();
            $defaultEventType = $decodedConfig->getLuEventType();
        } catch (MapperException $e) {
            /* Something went awry with loadConnectorConfiguration()
             * Can't determine what event type this would be
             */
            $defaultEventType = null;
        }

        /* Ensure $eventType is valid, finding first available one if it is invalid.
         * If you can't find one, we can't assign anything to the events from this subscription
         * so just return the empty stack; there's no way we could categorise those events
         *
         * getLuEventType() can return a deleted event type identifier.
         */
        $defaultEventType = $this->eventTypeMapper->findFirstMeaningfulId($defaultEventType);

        /* Do a detection on $allowedEventTypes, if provided and meaningful
         */
        if (null !== $allowedEventTypes && \count($allowedEventTypes)) {
            if (!\in_array((int)$defaultEventType, $allowedEventTypes, true)) {
                return $stack;
            }
        }

        try {
            $externalEvents = $this->externalEvents->getExternalEvents($this->getUser(), $startTimestamp, $endTimestamp);
        } catch (InvalidArgumentException $e) {
            return $stack;
        }

        foreach ($externalEvents as $externalEvent) {
            $event = new Event();
            $event->setEventId($externalEvent['id']);

            /* Connector-specific event type mapping
             *
             * Assume $externalEvent['type'] to be the truth and try to match its value to a luEventType
             *
             * If $externalEvent['type'] cannot be found, use the calendar configuration value that we chose in admin
             *
             * If THAT option didn't exist (i.e. deleted after chosen) then just default to DEFAULT_EVENTTYPE_ID
             */
            try {
                $eventType = $this->eventTypeMapper->findOneByColumn('eventType', $externalEvent['type'])->getId();
                $event->setType($eventType);
            } catch (MapperException $e) {
                $event->setType($defaultEventType);
            }

            /* All other event details
             */
            $event->setTitle($externalEvent['title']);
            $event->setIsEditable(false);
            $event->setStartTime(new Alaress_Date_Format($externalEvent['start'], Alaress_Date_Format::DATABASE_FORMAT));
            $event->setEndTime(new Alaress_Date_Format($externalEvent['finish'], Alaress_Date_Format::DATABASE_FORMAT));

            // Publish date is not available in Synergetic Staff Scheduler, but may be available elsewhere
            if (empty($externalEvent['publish'])) {
                $publishDate = '';
            } else {
                $publishDate = new Alaress_Date_Format($externalEvent['finish'], Alaress_Date_Format::DATABASE_FORMAT);
            }

            $event->setPublishDate($publishDate);

            //Where is the best place to format this? TODO
            $eventDetail = '';
            if (strlen(strip_tags($externalEvent['comment'])) > 0) {
                $eventDetail .= strip_tags($externalEvent['comment']);
            }
            if (strlen($externalEvent['team']) > 0) {
                $eventDetail .= '<br />Team: ' . $externalEvent['team'];
            }
            if (strlen($eventDetail) > 0) {
                $event->setDetail('<p>' . $eventDetail . '</p>');
            }

            /* Determine event location
             */
            if (strlen($externalEvent['location'])) {
                $location = $externalEvent['location'];
            } else {
                $location = $externalEvent['room'];
            }

            $event->setLocation($location);

            $event->setEventType(self::EXTERNAL_EVENT_TYPE);
            $event->setAllDay(false);

            /* If you can't find this user by external id, assume unknown
             */
            try {
                $owner = $this->userMapper->findByExternalId($externalEvent['owner']);
            } catch (MapperException $e) {
                $owner = $this->userMapper->getUnknownUser();
            }

            $event->setAuthorId($externalEvent['owner']);
            $event->setAuthorName($owner->formatName());

            /* Assign event colours - rely on cache, sorry :/
             */
            try {
                /** @var EventType $eventTypeObject */
                $eventTypeObject = $this->eventTypeMapper->findById($event->getType());
                $event->setColour($eventTypeObject->getColor());
            } catch (MapperException $e) {
                $event->setColour(null);
            }

            $event->setConnectorId($this->getConnectorId());

            $stack->add($event);
        }

        return $stack;
    }

    /**
     * Making the abstract class happy
     * @param int $eventId
     * @return string
     */
    public function getEvent($eventId)
    {
        return '';
    }
}
