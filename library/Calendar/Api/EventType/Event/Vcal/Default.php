<?php

/**
 * Calendar_Api_EventType_Event_Vcal_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use Calendar\Event\Collection;
use Calendar\Event\Type\Day\Event;
use Schoolbox\User;

class Calendar_Api_EventType_Event_Vcal_Default extends Calendar_Api_EventType_Abstract
{

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
    }

    /**
     * Associate a colour scheme with this event stream
     *
     * @param $id
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
     * @param $startTimestamp
     * @param $endTimestamp
     *
     * @internal param $currentDay
     * @return Collection
     */
    public function getEvents($startTimestamp, $endTimestamp)
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
            $endTimestamp
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
        $limit = null
    ) {

        $collection = new Collection();

        $currentConfig = $this->loadConnectorConfiguration();
        $decodedConfig = $currentConfig->decodeConfig();

        /* Check URL is set */
        if(!$decodedConfig->getUrl()) {
            return new Collection();
        }

        $url = $this->tokenReplaceUrl($decodedConfig->getUrl());

        /* Check permissions - if you can view these events according to the Permissions
         * settings then accumulate the events. Otherwise ignore them.
         * (Superusers can view all events, so skip permissions on those)
         */
        if ($this->getUser()->isSuperuser()
            || $decodedConfig->hasPermissions(
                $this->getUser()->getCampusCollection()->getColumnValues('id'),
                $this->getUser()->getRole()->getId()
            )
        ) {

            /* Parse it all
             */
            try {
                /* Load calendar from locker cache
                * Cache step 1: cache load of VCAL file from URL
                */
                $cache  = new Calendar_Api_Cache();
                $source = $cache->init($url)->getStreamContents();

                // Cache step 2: Expand VCAL into event collection.
                // The expanded events depend on the start and end time, not
                // just the VCAL file.
                /** @var \Sabre\VObject\Document $allEvents */
                $allEvents = Alaress\Redis::getInstance()->cacheFunctionCall(
                    'vcal.' . $url,
                    function ($params) {
                        [$source, $startTimestamp, $endTimestamp] = $params;
                        // You provided UTF-16? Proceed to UTF-8
                        if (preg_match('/^(\x{FF}\x{FE})|(\x{FE}\x{FF})/', $source)) {
                            $source = mb_convert_encoding($source, 'UTF-8', 'UTF-16');
                        } else {
                            // Just remove UTF8 BOM if it exists
                            $source = preg_replace('/^(\x{EF}\x{BB}\x{BF})/', '', $source);
                        }

                        $allEvents = Sabre\VObject\Reader::read(
                            $source,
                            Sabre\VObject\Reader::OPTION_FORGIVING
                        );
                        if ($startTimestamp && $endTimestamp) {
                            $allEvents = $allEvents->expand(
                                new DateTime('@' . $startTimestamp),
                                new DateTime('@' . $endTimestamp)
                            );
                        }
                        return $allEvents;
                    },
                    [$source, $startTimestamp, $endTimestamp],
                    $cache->getCachedTime()
                );

                // If that returned a string: it's retrieving a cached serialized version.
                // Unserialize it.
                // TODO remove this check in a later version
                if (is_string($allEvents)) {
                    $allEvents = unserialize($allEvents);
                }
            } catch (Exception $e) {
                /* Some google calendars don't follow sabre's expected format?
                echo $decodedConfig->getUrl();
                echo $e->getMessage();
                 */
            }

            if ($allEvents !== null) {
                foreach ($allEvents->getComponents() as $currentEvent) {
                    /* gloop returns a whole bunch of stuff all mixed up
                     *
                     * We only want to deal with Sabre\VObject\Component\VEvent objects
                     * as they seem to be the calendar items themselves
                     */
                    if ($currentEvent instanceof Sabre\VObject\Component\VEvent) {
                        /** @var $currentEvent \Sabre\VObject\Component\VEvent */

                        $isAdded = true;
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
     * @return Calendar_Event_Type_Day
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
            $metaDetails = $this->findLuEventType($decodedConfig->getLuEventType());
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

        /* Give the calendar_connector id so this event knows where it came from
         * This allows you to create an event detail screen in Mobile
         * (and also filter by event source on main calendar)
         */
        $event->setConnectorId($this->getConnectorId());

        return $event;
    }

    /**
     * This function replaces tokens in the URL with user parameters, to allow personalisation of VCAL URL
     * @param  string  $url
     * @return string
     */
    private function tokenReplaceUrl($url)
    {
        $urlTokens = [
            '[id]',
            '[externalId]',
            '[username]' ,
            '[email]' ,
        ];

        $urlReplacement = [
            $this->getUser()->getId(),
            $this->getUser()->getExternalId(),
            $this->getUser()->getUsername(),
            $this->getUser()->getEmail()
        ];

        return str_ireplace($urlTokens, $urlReplacement, $url);
    }
}
