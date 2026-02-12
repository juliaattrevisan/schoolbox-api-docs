<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\TimetableCalendar;

use DebugBar\DebugBarException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Connector\ClassCode;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Factory;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\ClassIndex;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\Enrolments;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\MeetingTimesConcurrent;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\Users;
use Schoolbox\External\Connector\Watchlist;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Event\Model;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\Mapper\Exception;

/**
 * Import all the classes for a student, saving them as sis_event records
 * with the 'timetable' classification
 */
class Classes implements IStrategy
{
    /**
     * The scopes we used for manufacturing a Schedule Timetable
     */
    public const string SCOPE = '
        classes:list
        academics.enrollments:list
        academics.classes.meeting_times:list
    ';


    /**
     * classes:list produces a giant pile of data of LITERALLY EVERYTHING (e.g. Clipboard teams etc) which makes for a
     * very large list; we obtain Meeting Times for all of these. We don't need all of that data; we just need a subset.
     * These course types allow you to delineate these types of "classes"
     *
     * This is shared among multiple lookups.
     *
     * TODO Move to academics.classes:list instead
     */
    public const array CLASS_COURSE_TYPES = [
        5, // Academic
        /* TODO Unlock these progressively
            2, // Advisory (not sure what this really means atm)
        */
        1, // Homeroom
    ];


    /**
     * A list of classEntries that we saw previously
     *
     * @var array
     */
    private array $classEntries = [];


    /**
     * A list of classInvolvements that we saw previously
     *
     * @var array
     */
    private array $classInvolvements = [];


    /**
     * Special event query generator that saves partial rows
     *
     * @var EventHandler
     */
    private EventHandler $eventHandler;


    /**
     * Cache for events created/reused by guid.
     *
     * @var array<string, Model>
     */
    private array $eventsByGuid = [];

    /**
     * Constructor.
     *
     * @param LoggerInterface $log
     * @param bool $perform
     * @param IApi $api
     * @param Watchlist $watcher
     * @param array $watchlist
     * @param Mapper $eventMapper
     * @param ClassEntryMapper $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     * @param \Alaress_Date_Format $fromDate
     * @param \Alaress_Date_Format $toDate
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private IApi $api,
        private Watchlist $watcher,
        private array $watchlist,
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private ClassEntryMapper $classEntryMapper,
        private ClassInvolvementMapper $classInvolvementMapper,
        private \Alaress_Date_Format $fromDate,
        private \Alaress_Date_Format $toDate
    ) {
        /* Event Chandler that saves a subset of event records' columns
         * to minimise changes to the db, due to db ignoring the indexes
         */
        $this->eventHandler = new EventHandler($eventMapper, $log);
    }


    /**
     * Import timetable classes and persist them as sis_event records with the "timetable" classification.
     *
     * The import batches students by class instance (guid) so each event and staff involvement is only
     * written once, then student enrolments are attached.
     *
     * @throws DebugBarException
     * @throws Exception
     * @throws \JsonException
     * @throws \Throwable
     */
    public function import(): void
    {
        $start = microtime(true);

        $this->log->info('Processing timetables.');

        $this->log->info(sprintf(
            'Processing classes between %s and %s',
            $this->fromDate,
            $this->toDate
        ));

        $userLookup = (new Users($this->watcher, $this->watchlist, $this->eventMapper))->generate();
        $enrolmentsLookup = (new Enrolments($this->api, $this->watchlist, $this->log))->generate();
        $classesLookup = (new ClassIndex($this->api, $this->watchlist, $enrolmentsLookup, $this->log))->generate();

        $startDate = $this->fromDate->getDatabaseDate();
        $endDate = $this->toDate->getDatabaseDate();

        $meetingTimesLookup = (new MeetingTimesConcurrent($this->api, array_keys($classesLookup), $this->log))
            ->generate($startDate, $endDate);

        $studentsByClassId = $this->buildStudentsByClassId($enrolmentsLookup);
        $eventsToPersist = $this->buildEventsToPersist($meetingTimesLookup, $studentsByClassId, $classesLookup);

        foreach ($eventsToPersist as $guid => $payload) {
            $classInfo = $payload['info'];
            $classInstance = $payload['instance'];
            $classId = $payload['classId'];

            $event = $this->getOrCreateEventByGuid($guid, $classInfo, $classInstance);
            $classEntry = $this->createClassEntry($classInfo, $event);

            $this->applyTeacherInvolvement($classInfo, $event, $classEntry);

            foreach (array_keys($studentsByClassId[$classId]) as $studentExternalId) {
                $name = $this->getUserDisplayName($userLookup, (string)$studentExternalId);
                $this->saveEnrolment($event, $classEntry, $name, (string)$studentExternalId);
            }
        }

        $end = microtime(true);

        $this->log->debug(sprintf(
            'Timetable import duration: %s seconds.',
            number_format($end - $start, 2)
        ));
    }

    /**
     * Reverse index of class id => set of student external ids.
     *
     * @param array $enrolmentsLookup
     * @return array<string, array<string, bool>>
     */
    private function buildStudentsByClassId(array $enrolmentsLookup): array
    {
        $studentsByClassId = [];

        foreach ($enrolmentsLookup as $externalId => $enrolmentsByClass) {
            foreach ($enrolmentsByClass as $classId => $_enrolment) {
                $studentsByClassId[(string)$classId][(string)$externalId] = true;
            }
        }

        return $studentsByClassId;
    }

    /**
     * Build a guid-keyed list of class instances to persist.
     *
     * Students are NOT attached here; they are expanded from $studentsByClassId at persist time.
     *
     * @param array $meetingTimesLookup
     * @param array<string, array<string, bool>> $studentsByClassId
     * @param array $classesLookup
     * @return array<string, array{info: array, instance: array, classId: string}>
     */
    private function buildEventsToPersist(array $meetingTimesLookup, array $studentsByClassId, array $classesLookup): array
    {
        $eventsToPersist = [];

        foreach ($meetingTimesLookup as $classId => $meetingTimes) {
            $classId = (string)$classId;

            if (!isset($studentsByClassId[$classId], $classesLookup[$classId])) {
                continue;
            }

            $classInfo = $this->buildClassInfo($classId, $classesLookup);

            foreach ($meetingTimes as $classInstance) {
                $guid = $this->buildGuid($classInfo, $classInstance);

                $eventsToPersist[$guid] ??= [
                    'info' => $classInfo,
                    'instance' => $classInstance,
                    'classId' => $classId,
                ];
            }
        }

        return $eventsToPersist;
    }

    /**
     * Build a friendly display name for logging.
     */
    private function getUserDisplayName(array $userLookup, string $externalId): string
    {
        $firstname = $userLookup[$externalId]['firstname'] ?? '';
        $lastname = $userLookup[$externalId]['lastname'] ?? '';

        $name = trim(sprintf('%s %s', $firstname, $lastname));

        return $name !== '' ? $name : $externalId;
    }

    /**
     * Build the class info structure expected by createEvent() and applyTeacherInvolvement().
     *
     * This is derived from classes:list payload (via ClassIndex), avoiding enrolments-derived metadata.
     *
     * @param string $classId
     * @param array  $classesLookup
     * @return array{internal_class_id:string, name:string, code:mixed, teachers:array<int, array{id:mixed, name:string}>}
     */
    private function buildClassInfo(string $classId, array $classesLookup): array
    {
        $classRecord = $classesLookup[$classId] ?? [];

        $name = trim((string)($classRecord['description'] ?? ''));
        $code = $classRecord['class_id'] ?? null;

        $teachers = [];
        $primaryTeacher = $classRecord['primary_teacher'] ?? null;
        if (is_array($primaryTeacher) && isset($primaryTeacher['id'])) {
            $teacherName = trim((string)($primaryTeacher['name'] ?? ''));

            $teachers = [
                [
                    'id' => $primaryTeacher['id'],
                    'name' => $teacherName,
                ],
            ];
        }

        return [
            'internal_class_id' => $classId,
            'name' => $name,
            'code' => $code,
            'teachers' => $teachers,
        ];
    }


    /* NOTE: Legacy per-student processing helpers removed.
     * The import now batches by guid and persists each class instance once.
     */

    /**
     * Build a stable identifier representing a specific class instance.
     *
     * @param array $class         Class info array containing internal_class_id.
     * @param array $classInstance Class instance array containing start/end ADF objects.
     */
    private function buildGuid(array $class, array $classInstance): string
    {
        return sprintf(
            '%s_%s_%s',
            $class['internal_class_id'],
            $classInstance['start']->getDatabase(),
            $classInstance['end']->getDatabase()
        );
    }

    /**
     * Create (or reuse) an event per guid to avoid repeated DB work across many students.
     *
     * @param string $guid
     * @param array  $class
     * @param array  $classInstance
     *
     * @throws DebugBarException
     * @throws Exception
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function getOrCreateEventByGuid(string $guid, array $class, array $classInstance): Model
    {
        if (isset($this->eventsByGuid[$guid])) {
            return $this->eventsByGuid[$guid];
        }

        $event = $this->createEvent($class, $classInstance, $guid);
        $this->eventsByGuid[$guid] = $event;

        return $event;
    }

    /**
     * Create a SUDS Timetable Event with all the class and lesson pieces.
     *
     * If an event already exists for the given guid, it will be reused (and selectively updated/saved).
     *
     * @param array       $class
     * @param array       $classInstance
     * @param string|null $guid Optional guid; if omitted it will be built from class + instance time window.
     *
     * @throws DebugBarException
     * @throws Exception
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createEvent(
        array $class,
        array $classInstance,
        ?string $guid = null
    ): Model {
        /* Fill in these vars as usual
         */
        $classCode = new ClassCode($this->log)->generate(trim((string)$class['code']));
        $day = trim((string)$classInstance['day']['description']);
        $detail = null;

        /* Arbitrarily manufacture a guid for now, which represents
         * a class whose instance is happening at a particular time
         */
        $guid = $guid ?? $this->buildGuid($class, $classInstance);

        /* Put a Session Time in here, in accordance with Haines' feature request
         * I *think* this might be the $classInstance['block'] but could be wrong.
         */
        $period = $classInstance['block']['description'] ?? '';

        /* Decide where this class happens
         */
        $room = null;

        if (is_array($classInstance['room'])) {
            $room = $classInstance['room']['description'];
        }

        $title = trim((string)$class['name']);

        /* This is pretty optional - this is the sis' equivalent of our luEventType
         * Sometimes it's provided, sometimes it's not - we can record it here if
         * possible. If you can't get it, dw about it
         */
        $type = '';

        /* Convert strings to adf
         *
         * Apply timezone settings to these zulu-time dates
         */
        $this->log->debug(sprintf(
            'Original dates: %s - %s',
            $classInstance['start_time'],
            $classInstance['end_time']
        ));

        /* Weird hacky version for now. Revert when veracross repairs their data.
         */
        $startAt = $classInstance['start'];
        $finishAt = $classInstance['end'];

        /* Try to recycle based on guid
         */
        try {
            $oldEvent = $this->eventMapper->findOneByColumn('guid', $guid);

            $logPrefix = 'Reusing existing';

            $this->log->debug(sprintf(
                'Found previously existing sis_event instance for guid %s',
                $guid
            ));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /* Initialise props before trying to access them to avoid fatals like
             * "$startAt must not be accessed before initialization"
             */
            $oldEvent = $this->eventMapper->createModel()
                ->setStartAt(null)
                ->setFinishAt(null);

            $logPrefix = 'Creating new';
        }

        /* Cram in everything else
         *
         * Calculate a diff to see if anything changed before saving it
         * to minimise transaction size
         */
        $event = clone $oldEvent;

        $event
            ->setTitle($title)
            ->setDetail($detail)
            ->setLocation($room)
            ->setCode($classCode)
            ->setDay($day)
            ->setPeriod($period)
            ->setStartAt($startAt)
            ->setFinishAt($finishAt)
            ->setType($type)
            ->setMetadata((new Metadata\Builder())->build($this->api->getCredentials(), $class))
            ->setClassification(Model::CLASSIFICATION_TIMETABLE)
            ->setGuid($guid)
            ->setSource(Factory::SOURCE_NAME);

        /* Save it and remember what we saw in case we need to reuse this later
         */
        $this->log->info(sprintf(
            '%s timetable event %s - %s, %s, %s-%s',
            $logPrefix,
            $event->getTitle(),
            $event->getDay(),
            $event->getPeriod(),
            $event->getStartAt()->getDatabase(),
            $event->getFinishAt()->getDatabase()
        ));

        /* Only persist if it's different than before
         */
        if ($this->perform) {
            $this->eventHandler->save($event, $oldEvent);
        }

        return $event;
    }


    /**
     * Create (or reuse) a ClassEntry corresponding to an event.
     *
     * Cached by event code so the DB write only happens once per class code.
     *
     * @param array $class Class info array.
     * @param Model $event
     *
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function createClassEntry(array $class, Model $event): ClassEntry
    {
        if (isset($this->classEntries[$event->getCode()])) {
            $classEntry = $this->classEntries[$event->getCode()];
        } else {
            $classEntry = $this->classEntryMapper->createModel()
                ->setName($class['name'])
                ->setCode($class['code']);

            if ($this->perform) {
                $this->classEntryMapper->save($classEntry);
            }

            $this->classEntries[$event->getCode()] = $classEntry;
        }

        return $classEntry;
    }

    /**
     * Apply teacher involvement and staff overrides for a timetable event.
     *
     * This is expected to be executed once per event (guid) by the import pipeline.
     *
     * @param array      $class
     * @param Model      $event
     * @param ClassEntry $classEntry
     *
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function applyTeacherInvolvement(
        array $class,
        Model $event,
        ClassEntry $classEntry
    ): void {
        if (!isset($class['teachers'][0])) {
            return;
        }

        $teacherExternalId = trim((string)$class['teachers'][0]['id']);
        $name = trim((string)$class['teachers'][0]['name']);

        $this->log->info(sprintf(
            'Creating new class teacher involvement for %s (%s) in timetable event %s - %s, %s, %s-%s',
            $teacherExternalId,
            $name,
            $event->getTitle(),
            $event->getDay(),
            $event->getPeriod(),
            $event->getStartAt()->getDatabase(),
            $event->getFinishAt()->getDatabase()
        ));

        $this->saveEnrolment($event, $classEntry, $name, $teacherExternalId);
    }

    /**
     * Save a class enrolment for the given class entry.
     *
     * Uses an in-memory cache per (classEntryId, externalId) to prevent duplicate writes.
     *
     * @param Model      $event
     * @param ClassEntry $classEntry
     * @param string     $name Human-friendly name used for logging.
     * @param string     $externalId
     *
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function saveEnrolment(
        Model $event,
        ClassEntry $classEntry,
        string $name,
        string $externalId
    ): void {
        // Name can be blank if user lookup didn't contain that person; fall back for clarity in logs.
        $displayName = trim($name);
        if ($displayName === '') {
            $displayName = $externalId;
        }

        if (isset($this->classInvolvements[$classEntry->getId()][$externalId])) {
            $this->log->debug(sprintf(
                'Reusing previous class enrolment for %s (%s) in timetable event %s - %s, %s, %s-%s',
                $displayName,
                $externalId,
                $event->getTitle(),
                $event->getDay(),
                $event->getPeriod(),
                $event->getStartAt()->getDatabase(),
                $event->getFinishAt()->getDatabase()
            ));
            return;
        }

        $classInvolvement = $this->classInvolvementMapper->createModel()
            ->setClass($classEntry)
            ->setExternalId($externalId);

        if ($this->perform) {
            $this->classInvolvementMapper->save($classInvolvement);
        }

        $this->classInvolvements[$classEntry->getId()][$externalId] = true;

        $this->log->info(sprintf(
            'Creating new class enrolment for %s (%s) in timetable event %s - %s, %s, %s-%s',
            $displayName,
            $externalId,
            $event->getTitle(),
            $event->getDay(),
            $event->getPeriod(),
            $event->getStartAt()->getDatabase(),
            $event->getFinishAt()->getDatabase()
        ));
    }
}
