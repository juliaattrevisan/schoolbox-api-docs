<?php

/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Database\Iterator\IIterator;
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
     * Default id for an event type that was chosen in admin, but doesn't exist anymore
     *
     * @var int
     */
    private const DEFAULT_EVENTTYPE_ID = 1;

    /**
     * A string to indicate the source from which this event emerged
     *
     * @var string
     */
    private const EXTERNAL_EVENT_TYPE = 'external';

    /**
     * An external event handler instance
     *
     * @var Calendar
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
     * @throws MapperException
     */
    public function getEvents($startTimestamp, $endTimestamp)
    {

        $stack = new Collection();
        /** @var IIterator $externalEvents */
        $externalEvents = $this->externalEvents->getExternalEvents($this->getUser(), $startTimestamp, $endTimestamp);

        // Figure out default event type
        try {
            /** @var Calendar_Api_Config_Record $config */
            $config = $this->loadConnectorConfiguration();
            $decodedConfig = $config->decodeConfig();
            $defaultEventType = $decodedConfig->getLuEventType();
        } catch (MapperException $e) {
            $defaultEventType = self::DEFAULT_EVENTTYPE_ID;
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
