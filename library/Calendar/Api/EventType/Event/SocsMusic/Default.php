<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Calendar\Event\Type\Day\Event;
use Calendar\Event\Type\EventInterface;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Role;
use Schoolbox\Socs\HttpClient;
use Schoolbox\Socs\Music\Lesson;
use Schoolbox\Socs\Music\Parser;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Sentry\State\HubInterface;

class Calendar_Api_EventType_Event_SocsMusic_Default extends Calendar_Api_EventType_Abstract
{
    public const string TUITION_PATH = '/socs/xml/tuition.ashx';

    private const string LESSON_DATA_TYPE = 'musiclessons';

    /**
     * @var array<int, array{eventTypeID: int|string, eventType: string, eventColour: string}>
     */
    private array $allLuEventTypes;

    /**
     * @var array<string, User|null>
     */
    private array $userLookup = [];

    public function __construct(
        int $connectorId,
        User $user,
        User $viewer,
        private IConfigLoader $configLoader,
        private EventTypeMapper $eventTypeMapper,
        private HttpClient $httpClient,
        private UserMapper $userMapper,
        ?array $allLuEventTypes = null,
        private ?HubInterface $sentry = null,
        private ?Calendar_Api_Config_Mapper $configMapper = null,
    ) {
        parent::__construct($connectorId, $user, $viewer);
        $this->allLuEventTypes = $allLuEventTypes ?? (new Calendar())->getTypeList();
    }

    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): GenericCollection
    {
        return $this->getFilteredEventCollection(
            static fn (Lesson $lesson): bool => !(
                $lesson->end->getTimestamp() < $startTimestamp
                || $lesson->start->getTimestamp() >= $endTimestamp
            ),
            null,
            $allowedEventTypes,
        );
    }

    public function getEvent($eventId): GenericCollection
    {
        return $this->getFilteredEventCollection(
            static fn (Lesson $lesson): bool => (string)$eventId === self::buildEventId($lesson),
            1,
        );
    }

    /**
     * @param callable(Lesson): bool $filterFunction
     * @param int[]|null            $allowedEventTypes
     */
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

        try {
            $lessons = $this->readLessons();
        } catch (\JsonException | \RuntimeException | \Sabre\VObject\ParseException $e) {
            $this->reportFeedFailure($e);

            return $collection;
        }

        foreach ($lessons as $lesson) {
            if (!$filterFunction($lesson)) {
                continue;
            }

            $staff = $this->findUser($lesson->staffExternalId, Role::STAFF);
            $student = $this->findUser($lesson->studentExternalId, Role::STUDENT);
            if (!$this->isVisibleForCalendarUser($staff, $student)) {
                continue;
            }

            $collection->add($this->buildCalendarEvent($lesson, $eventTypeId, $staff, $student));
            if (isset($limit) && $collection->count() >= $limit) {
                return $collection;
            }
        }

        return $collection;
    }

    private function isConfigured(): bool
    {
        return (bool)$this->configLoader->get(ConfigMapper::NAME_SOCS_ENABLED)
            && (bool)$this->configLoader->get(ConfigMapper::NAME_SOCS_IMPORT_MUSIC_LESSONS)
            && (string)$this->configLoader->get(ConfigMapper::NAME_SOCS_ENDPOINT_HOST) !== ''
            && (string)$this->configLoader->get(ConfigMapper::NAME_SOCS_ENDPOINT_ID) !== ''
            && (string)$this->configLoader->get(ConfigMapper::NAME_SOCS_ENDPOINT_TOKEN) !== '';
    }

    /**
     * @return Lesson[]
     * @throws \JsonException|\RuntimeException|\Sabre\VObject\ParseException
     */
    private function readLessons(): array
    {
        $xml = $this->httpClient->getCached(self::TUITION_PATH, [
            'data' => self::LESSON_DATA_TYPE,
        ]);

        return (new Parser())->parse($xml);
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

    private function getConfiguredEventTypeId(): int
    {
        return (int)$this->configLoader->get(ConfigMapper::NAME_SOCS_MUSIC_LESSONS_DEFAULT_EVENT_TYPE);
    }

    private function buildCalendarEvent(Lesson $lesson, int $eventTypeId, ?User $staff, ?User $student): Event
    {
        $metaDetails = $this->findLuEventType($this->eventTypeMapper->findFirstMeaningfulId($eventTypeId));

        $event = new Event();
        $event->setAuthorName('');
        $event->setAuthorId(-1);
        $event->setEventId(self::buildEventId($lesson));
        $event->setStartTime(new Alaress_Date_Format($lesson->start->getTimestamp(), 'U'));
        $event->setEndTime(new Alaress_Date_Format($lesson->end->getTimestamp(), 'U'));
        $event->setType($metaDetails['eventTypeID']);
        $event->setTitle($lesson->title);
        $event->setAccess(Event::PERSONAL_ACCESS);
        $event->setEventType($metaDetails['eventType']);
        $event->setColour($metaDetails['eventColour']);
        $event->setDetail($this->buildDetail($lesson));
        $event->setLocation($lesson->location);
        $event->setPublishDate(null);
        $event->setAllDay(false);
        $event->setIsEditable(false);
        $event->setAttendance(0);
        $event->setCompleted(false);
        $event->setConnectorId($this->getConnectorId());
        $event->setSocsMusicStaff($staff instanceof User ? [$this->formatParticipant($staff)] : []);
        $event->setSocsMusicStudents($student instanceof User ? [$this->formatParticipant($student)] : []);

        return $event;
    }

    private static function buildEventId(Lesson $lesson): string
    {
        return 'socs-music-' . $lesson->id;
    }

    private function isVisibleForCalendarUser(?User $staff, ?User $student): bool
    {
        $calendarUserId = $this->getUser()->getId();

        return ($staff instanceof User && $staff->getId() === $calendarUserId)
            || ($student instanceof User && $student->getId() === $calendarUserId);
    }

    private function findUser(string $externalId, string $roleType): ?User
    {
        if ($externalId === '') {
            return null;
        }

        $cacheKey = $roleType . ':' . $externalId;
        if (array_key_exists($cacheKey, $this->userLookup)) {
            return $this->userLookup[$cacheKey];
        }

        try {
            $this->userLookup[$cacheKey] = $this->userMapper->findByExternalId($externalId, $roleType);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->userLookup[$cacheKey] = null;
        }

        return $this->userLookup[$cacheKey];
    }

    /**
     * @return array{id: int, name: string, url: string}
     */
    private function formatParticipant(User $user): array
    {
        return [
            'id' => (int)$user->getId(),
            'name' => $this->formatUserName($user),
            'url' => '/search/user/' . $user->getId(),
        ];
    }

    private function formatUserName(User $user): string
    {
        return trim(sprintf(
            '%s %s',
            $user->getPreferredName() ?: $user->getFirstname(),
            $user->getLastname()
        ));
    }

    private function buildDetail(Lesson $lesson): string
    {
        if ($lesson->instrument === '') {
            return '';
        }

        return 'Instrument: ' . htmlspecialchars($lesson->instrument, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
