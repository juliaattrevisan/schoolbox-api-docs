<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Calendar\Event\Type\Day\Event;
use Calendar\Event\Type\EventInterface;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\CampusMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Socs\Calendar\CampusMatcher;
use Schoolbox\Socs\Calendar\Parser;
use Schoolbox\Socs\Fixtures\Registry;
use Schoolbox\Socs\HttpClient;
use Schoolbox\User;
use Sentry\State\HubInterface;

/**
 * Calendar event source backed by the SOCS XML calendar feed.
 */
class Calendar_Api_EventType_Event_Socs_Default extends Calendar_Api_EventType_Abstract
{
    public const string CALENDAR_PATH = '/socs/xml/SOCScalendar.ashx';

    /**
     * @var array<int, array{eventTypeID: int|string, eventType: string, eventColour: string}>
     */
    private array $allLuEventTypes;

    public function __construct(
        int $connectorId,
        User $user,
        User $viewer,
        protected IConfigLoader $configLoader,
        private EventTypeMapper $eventTypeMapper,
        private CampusMapper $campusMapper,
        private HttpClient $httpClient,
        ?array $allLuEventTypes = null,
        private ?HubInterface $sentry = null,
        private ?Calendar_Api_Config_Mapper $configMapper = null,
    ) {
        parent::__construct($connectorId, $user, $viewer);
        $this->allLuEventTypes = $allLuEventTypes ?? (new Calendar())->getTypeList();
    }

    /**
     * Retrieve SOCS calendar events that overlap the requested calendar window.
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): GenericCollection
    {
        return $this->getFilteredEventCollection(
            static fn (\Schoolbox\Socs\Calendar\Event $event): bool => !(
                $event->end->getTimestamp() < $startTimestamp
                || $event->start->getTimestamp() >= $endTimestamp
            ),
            null,
            $allowedEventTypes,
            $startTimestamp,
            $endTimestamp,
        );
    }

    /**
     * Retrieve one SOCS calendar event by its feed UID.
     */
    public function getEvent($eventId): GenericCollection
    {
        return $this->getFilteredEventCollection(
            static fn (\Schoolbox\Socs\Calendar\Event $event): bool => (string)$eventId === $event->uid,
            1,
        );
    }

    /**
     * Read SOCS feed events, apply caller-specific filtering, and convert matches to Schoolbox events.
     *
     * The filter excludes events that do not belong in the caller's view. For date windows, that means
     * excluding events that end before the window starts or start at/after the window ends.
     *
     * @param callable(\Schoolbox\Socs\Calendar\Event): bool $filterFunction
     * @param int|null                                          $limit
     * @param int[]|null                                        $allowedEventTypes
     */
    private function getFilteredEventCollection(
        callable $filterFunction,
        ?int $limit = null,
        ?array $allowedEventTypes = null,
        ?int $startTimestamp = null,
        ?int $endTimestamp = null,
    ): GenericCollection {
        $collection = new GenericCollection(EventInterface::class);

        if (!$this->isConfigured()) {
            return $collection;
        }

        $eventTypeId = $this->getConfiguredEventTypeId();
        if (null !== $allowedEventTypes && count($allowedEventTypes) && !in_array($eventTypeId, $allowedEventTypes, true)) {
            return $collection;
        }

        $userCampusIds = $this->getUser()->getCampusCollection()->getPropertyValues('id');
        $allCampuses = $this->campusMapper->findAll();
        $matcher = new CampusMatcher();

        try {
            $sourceEvents = $this->readEvents($startTimestamp, $endTimestamp);
        } catch (\JsonException | \RuntimeException | \Sabre\VObject\ParseException $e) {
            $this->reportFeedFailure($e);

            return $collection;
        }

        foreach ($sourceEvents as $sourceEvent) {
            if (!$filterFunction($sourceEvent)) {
                continue;
            }

            $campusIds = $matcher->matchCampusIds($allCampuses, $sourceEvent->schools);
            if (!count($campusIds) || !count(array_intersect($userCampusIds, $campusIds))) {
                continue;
            }

            $collection->add($this->buildCalendarEvent($sourceEvent, $eventTypeId, $campusIds));
            if (isset($limit) && $collection->count() >= $limit) {
                return $collection;
            }
        }

        return $collection;
    }

    /**
     * Determine whether the SOCS calendar feed has enough configuration to be queried.
     */
    protected function isConfigured(): bool
    {
        return (bool)$this->configLoader->get(ConfigMapper::NAME_SOCS_ENABLED)
            && (bool)$this->configLoader->get(ConfigMapper::NAME_SOCS_IMPORT_CALENDAR_EVENTS)
            && (string)$this->configLoader->get(ConfigMapper::NAME_SOCS_ENDPOINT_HOST) !== ''
            && (string)$this->configLoader->get(ConfigMapper::NAME_SOCS_ENDPOINT_ID) !== ''
            && (string)$this->configLoader->get(ConfigMapper::NAME_SOCS_ENDPOINT_TOKEN) !== '';
    }

    /**
     * Read and parse SOCS calendar feed events for an optional date range.
     *
     * @return \Schoolbox\Socs\Calendar\Event[]
     * @throws \JsonException|\RuntimeException|\Sabre\VObject\ParseException
     */
    private function readEvents(?int $startTimestamp = null, ?int $endTimestamp = null): array
    {
        $xml = $this->httpClient->getCached(static::CALENDAR_PATH, $this->buildFeedQuery($startTimestamp, $endTimestamp));

        return (new Parser())->parse($xml);
    }

    /**
     * Build SOCS calendar feed query parameters for the calendar window.
     *
     * @return array<string,string>
     */
    private function buildFeedQuery(?int $startTimestamp, ?int $endTimestamp): array
    {
        if (null === $startTimestamp || null === $endTimestamp) {
            return [];
        }

        return [
            'startdate' => Alaress_Date_Format::newFromTimestamp($startTimestamp)->getUsingFormat(Registry::SOCS_DATE_FORMAT),
            'enddate' => Alaress_Date_Format::newFromTimestamp($endTimestamp)->getUsingFormat(Registry::SOCS_DATE_FORMAT),
        ];
    }

    /**
     * Convert a parsed SOCS calendar feed event into a Schoolbox calendar event.
     *
     * @param int[] $campusIds
     */
    private function buildCalendarEvent(
        \Schoolbox\Socs\Calendar\Event $sourceEvent,
        int $eventTypeId,
        array $campusIds,
    ): Event {
        $metaDetails = $this->findLuEventType($this->eventTypeMapper->findFirstMeaningfulId($eventTypeId));

        $event = new Event();
        $event->setAuthorName('');
        $event->setAuthorId(-1);
        $event->setEventId($sourceEvent->uid);
        $event->setStartTime(new Alaress_Date_Format($sourceEvent->start->getTimestamp(), 'U'));
        $event->setEndTime(new Alaress_Date_Format($this->getEndTimestamp($sourceEvent), 'U'));
        $event->setType($metaDetails['eventTypeID']);
        $event->setTitle($sourceEvent->title);
        $event->setAccess(Event::CAMPUS_ACCESS);
        $event->setEventType($metaDetails['eventType']);
        $event->setColour($metaDetails['eventColour']);
        $event->setCampusList(implode(',', $campusIds));
        $event->setDetail(nl2br($sourceEvent->description));
        $event->setLocation($sourceEvent->location);
        $event->setPublishDate(null);
        $event->setAllDay($sourceEvent->allDay);
        $event->setIsEditable(false);
        $event->setAttendance(0);
        $event->setCompleted(false);
        $event->setConnectorId($this->getConnectorId());

        return $event;
    }

    private function reportFeedFailure(\Throwable $e): void
    {
        if (str_contains($e->getMessage(), 'returned an empty payload')) {
            return;
        }

        $this->sentry?->captureException($e);
        $this->addConnectorFailedCount();
    }

    private function addConnectorFailedCount(): void
    {
        try {
            $mapper = $this->configMapper ?? Calendar_Api_Config_Mapper::getInstance();
            $config = $mapper->findById($this->getConnectorId());
            $config->addFailedCount();
            $mapper->save($config);
        } catch (\Throwable $e) {
            $this->sentry?->captureException($e);
        }
    }

    /**
     * Return the configured Schoolbox calendar category for SOCS events.
     */
    protected function getConfiguredEventTypeId(): int
    {
        return (int)$this->configLoader->get(ConfigMapper::NAME_SOCS_CALENDAR_DEFAULT_EVENT_TYPE);
    }

    /**
     * Return the inclusive Schoolbox end timestamp for timed and all-day SOCS events.
     */
    private function getEndTimestamp(\Schoolbox\Socs\Calendar\Event $event): int
    {
        if (!$event->allDay) {
            return $event->end->getTimestamp();
        }

        return max($event->end->getTimestamp(), strtotime('+1 day', $event->end->getTimestamp()) - 1);
    }

    /**
     * Find calendar event-type display metadata for the configured category.
     *
     * @return array{eventTypeID: int|string, eventType: string, eventColour: string}
     */
    private function findLuEventType(int $id): array
    {
        foreach ($this->allLuEventTypes as $currentLuEventType) {
            if ((int)$currentLuEventType['eventTypeID'] === $id) {
                return $currentLuEventType;
            }
        }

        return $this->allLuEventTypes[0] ?? ['eventTypeID' => $id, 'eventType' => '', 'eventColour' => ''];
    }
}
