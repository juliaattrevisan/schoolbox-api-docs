<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Redis;
use Calendar\Event\Collection;
use Calendar\Event\Type\Day\Event;
use Sabre\VObject\Component\VCalendar;
use Schoolbox\Calendar\Cache\Vcal;
use Schoolbox\User;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\ParseException;
use Schoolbox\Calendar\EventTypeMapper;

/**
 * Class Calendar_Api_EventType_Event_Vcal_Default
 */
class Calendar_Api_EventType_Event_Vcal_Default extends Calendar_Api_EventType_Abstract
{

    /**
     * This indicates the event was cancelled
     */
    public const STATUS_CANCELLED = 'CANCELLED';


    /**
     * @var EventTypeMapper
     */
    protected $eventTypeMapper;


    /**
     * A list of luEventType records. You can assign a "colour scheme" to Vcal events
     * using the luEventType table.
     *
     * @var array
     */
    private $allLuEventTypes;


    /**
     * @inheritdoc
     */
    public function __construct(int $connectorId, User $user, User $viewer)
    {
        parent::__construct($connectorId, $user, $viewer);
        $this->allLuEventTypes = (new Calendar())->getTypeList();

        /* This should be injected, but it means making a vast change to the constructor here
         * and in multiple other locations...
         */
        $this->eventTypeMapper = EventTypeMapper::getInstance();
    }

    /**
     * Associate a colour scheme with this event stream
     *
     * @param int $id
     *
     * @return array
     */
    private function findLuEventType($id)
    {
        foreach ($this->allLuEventTypes as $currentLuEventType) {
            if ($currentLuEventType['eventTypeID'] == $id) {
                return $currentLuEventType;
            }
        }

        return array();
    }


    /**
     * Get day events from this data source
     *
     * @param int        $startTimestamp
     * @param int        $endTimestamp
     * @param array|null $allowedEventTypes
     * @return Collection
     * @internal param $currentDay
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): Collection
    {
        return $this->getFilteredEventCollection(
            function ($currentEvent) use ($startTimestamp, $endTimestamp) {
                /** @var Sabre\VObject\Component\VEvent $currentEvent */
                $allDay              = !$currentEvent->DTSTART->hasTime();
                $eventStartTimestamp = strtotime("{$currentEvent->DTSTART}");

                // Event has no DTEND (anniversaries and reminders might not
                // have one)
                // Set it to same as DTSTART, I guess.
                $eventEnd = "{$currentEvent->DTEND}";
                if (strlen($eventEnd) > 0) {
                    $eventEndTimestamp = strtotime("{$currentEvent->DTEND}");
                    // According to VCAL standard: end of all day event
                    // is 12am next day
                    if ($allDay) {
                        $eventEndTimestamp -= 1;
                    }
                } else {
                    $eventEndTimestamp = $eventStartTimestamp;
                }

                /* Only get events that overlap the range specified
                 * Event's end time is before start time of span: ignore
                 * Event's start time is before end time of span: ignore
                 */
                return (!($eventEndTimestamp < $startTimestamp || $eventStartTimestamp >= $endTimestamp));
            },
            $startTimestamp,
            $endTimestamp,
            null,
            $allowedEventTypes
        );
    }

    /**
     * Find a particular event from this source
     * Do everything the same as usual, but only stack up one event (if you find it) and
     * return that instead of a collection of everything
     *
     * @param  int $eventId
     *
     * @return Collection
     */
    public function getEvent($eventId)
    {
        return $this->getFilteredEventCollection(
            function ($currentEvent) use ($eventId) {
                /** @var Sabre\VObject\Component\VEvent $currentEvent */
                return $eventId == "{$currentEvent->UID}";
            },
            null,
            null,
            1
        );
    }

    /**
     * Retrieves collection of events which match a filter function.
     *
     * @param  callable $filterFunction   Callback which accepts following parameters:
     *                                    Sabre\VObject\Component\VEvent: event to add
     *                                    Calendar_Api_Config_Connector_Vcal: event retriever's configuration
     *                                    Returns true if this event is accepted
     * @param  int      $startTimestamp   If set, only events after this are returned
     * @param  int      $endTimestamp     If set, only events before this are returned
     * @param  int      $limit            If set, only this many events are returned
     *
     * @return Collection
     */
    protected function getFilteredEventCollection(
        $filterFunction,
        $startTimestamp = null,
        $endTimestamp = null,
        $limit = null,
        ?array $allowedEventTypes = null
    ) {

        $collection = new Collection();

        $currentConfig = $this->loadConnectorConfiguration();
        $decodedConfig = $currentConfig->decodeConfig();

        /* Check URL is set, and it's a vCal connector */
        if (!$decodedConfig instanceof Calendar_Api_Config_Connector_Vcal) {
            return $collection;
        }
        if (!$decodedConfig->getUrl() || $currentConfig->isDead()) {
            return $collection;
        }

        /* Do a detection on $allowedEventTypes, if provided and meaningful
         */
        if (null !== $allowedEventTypes && \count($allowedEventTypes)) {
            if (!\in_array((int)$decodedConfig->getLuEventType(), $allowedEventTypes, true)) {
                return $collection;
            }
        }

        /* Check permissions - if you can view these events according to the Permissions
         * settings then accumulate the events. Otherwise ignore them.
         * (Superusers can view all events, so skip permissions on those)
         */
        if (
            $this->getUser()->isSuperuser()
            || $decodedConfig->hasPermissions(
                $this->getUser()->getCampusCollection()->getColumnValues('id'),
                $this->getUser()->getRole()->getId()
            )
        ) {
            // Hit up the cache
            $cache = new Vcal(Redis::getCacheClientInstance());

            $decodedConfig->tokenReplaceUrl($this->getUser());
            // In general, calendar config is cached when saved from the admin page.
            // However, when tokens are in the URL, a cache may not exist for the replaced URL
            if (!$cache->keyExists($decodedConfig)) {
                try {
                    $cache->write($decodedConfig);
                } catch (InvalidDataException | ParseException | Schoolbox\Application\Exception\Exception $e) {
                    $currentConfig->addFailedCount();
                    Calendar_Api_Config_Mapper::getInstance()->save($currentConfig);
                }
            }
            $allEvents = $cache->read($decodedConfig);

            if ($allEvents instanceof VCalendar) {
                $start = new DateTime('@' . $startTimestamp);
                $end   = new DateTime('@' . $endTimestamp);
                foreach ($allEvents->getComponents() as $currentEvent) {
                    /* gloop returns a whole bunch of stuff all mixed up
                     *
                     * We only want to deal with Sabre\VObject\Component\VEvent objects
                     * as they seem to be the calendar items themselves
                     */
                    if ($currentEvent instanceof Sabre\VObject\Component\VEvent) {
                        /** @var \Sabre\VObject\Component\VEvent $currentEvent */
                        $isAdded = $currentEvent->isInTimeRange($start, $end);
                        if (is_callable($filterFunction)) {
                            $isAdded = $filterFunction($currentEvent, $decodedConfig);
                        }
                        if ($isAdded) {
                            $collection->add(
                                $this->buildCalendarEventFromVevent($currentEvent, $decodedConfig)
                            );
                        }
                        if (isset($limit) && $collection->getCount() >= $limit) {
                            return $collection;
                        }
                    }
                }
            }
        }
        return $collection;
    }

    /**
     * Generic method for creating a calendar event from a VEvent object
     *
     * @param \Sabre\VObject\Component\VEvent    $currentEvent
     * @param Calendar_Api_Config_Connector_Vcal $decodedConfig
     *
     * @return Event
     * @throws \Sabre\VObject\InvalidDataException
     */
    protected function buildCalendarEventFromVevent(
        Sabre\VObject\Component\VEvent $currentEvent,
        Calendar_Api_Config_Connector_Vcal $decodedConfig
    ) {

        $allDay              = !$currentEvent->DTSTART->hasTime();
        $eventStartTimestamp = strtotime("{$currentEvent->DTSTART}");
        $eventEndTimestamp   = strtotime("{$currentEvent->DTEND}");

        // No end timestamp?
        if ($eventEndTimestamp === false) {
            if (isset($currentEvent->DURATION)) {
                // Use duration if there
                $eventEndDatetime = new DateTime();
                $eventEndDatetime->setTimestamp($eventStartTimestamp);
                $eventEndDatetime->add(\Sabre\VObject\DateTimeParser::parseDuration($currentEvent->DURATION));
                $eventEndTimestamp = $eventEndDatetime->getTimestamp();
            } elseif ($allDay) {
                // All day? end at end of day
                $eventEndTimestamp = strtotime('+1 day', $eventStartTimestamp);
            } else {
                // Otherwise: end and start at same time
                $eventEndTimestamp = $eventStartTimestamp;
            }
        }

        // According to VCAL standard: end of all day event is 12am next day
        if ($allDay) {
            $eventEndTimestamp -= 1;
            if ($eventEndTimestamp < $eventStartTimestamp) {
                $eventEndTimestamp = $eventStartTimestamp;
            }
        }

        // Get category list for this event.
        // If this event has a category which matches
        // any event type, then apply that event type's
        // color instead of the configured one.
        $categoryList = explode(';', "{$currentEvent->CATEGORIES}");
        $categoryList = array_map('trim', $categoryList);
        $categoryList = array_map('strtoupper', $categoryList);
        $categoryList = array_filter($categoryList, 'strlen');
        $metaDetails  = null;
        foreach ($categoryList as $categoryName) {
            foreach ($this->allLuEventTypes as $eventType) {
                if (strtoupper($eventType['eventType']) === $categoryName) {
                    $metaDetails = $eventType;
                    break;
                }
            }
            if (!empty($metaDetails)) {
                break;
            }
        }
        if (empty($metaDetails)) {
            /* Find the first meaningful luEventType since we didn't match by name previously
             */
            $eventTypeId = $this->eventTypeMapper->findFirstMeaningfulId($decodedConfig->getLuEventType());

            $metaDetails = $this->findLuEventType($eventTypeId);
        }

        $event = new Event();

        $event->setAuthorName('');
        $event->setAuthorId(-1);
        $event->setEventId("{$currentEvent->UID}");

        $event->setStartTime(new Alaress_Date_Format($eventStartTimestamp, 'U'));
        $event->setEndTime(new Alaress_Date_Format($eventEndTimestamp, 'U'));

        $event->setType($metaDetails['eventTypeID']);
        $event->setTitle("{$currentEvent->SUMMARY}");
        $event->setAccess('');
        $event->setEventType($metaDetails['eventType']);
        $event->setColour($metaDetails['eventColour']);
        $event->setCampusList(implode(',', $decodedConfig->getCampus()));
        $event->setDetail(nl2br("{$currentEvent->DESCRIPTION}"));
        $event->setLocation("{$currentEvent->LOCATION}");
        $event->setPublishDate('');
        $event->setAllDay($allDay);

        $event->setIsEditable(false);

        $event->setAttendance(0);
        $event->setCompleted(false);

        /* Decide whether this should display with custom styles
         *
         * Inactive events are synonymous with "cancelled" etc. where we need to indicate
         * this event is, for whatever reason, to be disregarded
         */
        if (self::STATUS_CANCELLED === (string)$currentEvent->STATUS) {
            $event->setActive(false);
        }

        /* Give the calendar_connector id so this event knows where it came from
         * This allows you to create an event detail screen in Mobile
         * (and also filter by event source on main calendar)
         */
        $event->setConnectorId($this->getConnectorId());

        return $event;
    }
}
