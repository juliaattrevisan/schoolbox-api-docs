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
use Schoolbox\Socs\HttpClient;
use Schoolbox\User;

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
        private IConfigLoader $configLoader,
        private EventTypeMapper $eventTypeMapper,
        private CampusMapper $campusMapper,
        private ?HttpClient $httpClient = null,
        ?array $allLuEventTypes = null,
    ) {
        parent::__construct($connectorId, $user, $viewer);
        $this->allLuEventTypes = $allLuEventTypes ?? (new Calendar())->getTypeList();
    }

    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): GenericCollection
    {
        return $this->getFilteredEventCollection(
            static fn (\Schoolbox\Socs\Calendar\Event $event): bool => !(
                $event->end->getTimestamp() < $startTimestamp
                || $event->start->getTimestamp() >= $endTimestamp
            ),
            null,
            $allowedEventTypes,
        );
    }

    public function getEvent($eventId): GenericCollection
    {
        return $this->getFilteredEventCollection(
            static fn (\Schoolbox\Socs\Calendar\Event $event): bool => (string)$eventId === $event->uid,
            1,
        );
    }

    private function getFilteredEventCollection(
        callable $filterFunction,
        ?int $limit = null,
        ?array $allowedEventTypes = null,
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

        foreach ($this->readEvents() as $sourceEvent) {
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

    private function isConfigured(): bool
    {
        return (bool)$this->configLoader->get(ConfigMapper::NAME_SOCS_ENABLED)
            && (bool)$this->configLoader->get(ConfigMapper::NAME_SOCS_IMPORT_CALENDAR_EVENTS)
            && (string)$this->configLoader->get(ConfigMapper::NAME_SOCS_ENDPOINT_HOST) !== ''
            && (string)$this->configLoader->get(ConfigMapper::NAME_SOCS_ENDPOINT_ID) !== ''
            && (string)$this->configLoader->get(ConfigMapper::NAME_SOCS_ENDPOINT_TOKEN) !== '';
    }

    /**
     * @return \Schoolbox\Socs\Calendar\Event[]
     */
    private function readEvents(): array
    {
        try {
            $xml = $this->getHttpClient()->get(self::CALENDAR_PATH, []);
            if (!$xml instanceof SimpleXMLElement) {
                return [];
            }

            return (new Parser())->parse($xml);
        } catch (Throwable) {
            return [];
        }
    }

    private function getHttpClient(): HttpClient
    {
        if (!$this->httpClient instanceof HttpClient) {
            $this->httpClient = new HttpClient(
                (string)$this->configLoader->get(ConfigMapper::NAME_SOCS_ENDPOINT_HOST),
                (string)$this->configLoader->get(ConfigMapper::NAME_SOCS_ENDPOINT_ID),
                (string)$this->configLoader->get(ConfigMapper::NAME_SOCS_ENDPOINT_TOKEN),
            );
        }

        return $this->httpClient;
    }

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

    private function getConfiguredEventTypeId(): int
    {
        return (int)$this->configLoader->get(ConfigMapper::NAME_SOCS_CALENDAR_DEFAULT_EVENT_TYPE);
    }

    private function getEndTimestamp(\Schoolbox\Socs\Calendar\Event $event): int
    {
        if (!$event->allDay) {
            return $event->end->getTimestamp();
        }

        return max($event->end->getTimestamp(), strtotime('+1 day', $event->end->getTimestamp()) - 1);
    }

    /**
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
