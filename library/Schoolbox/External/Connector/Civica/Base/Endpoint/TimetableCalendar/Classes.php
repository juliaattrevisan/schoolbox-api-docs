<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint\TimetableCalendar;

use DebugBar\DebugBarException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Connector\Civica\Base\Endpoint\Factory;
use Schoolbox\External\Connector\Civica\Base\Endpoint\IApi;
use Schoolbox\External\Connector\ClassCode;
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
     * Constructor.
     *
     * @param LoggerInterface                            $log
     * @param bool                                       $perform
     * @param IApi                                       $api
     * @param array                                      $watchlist
     * @param Mapper                                     $eventMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     * @param ClassEntryMapper                           $classEntryMapper
     * @param ClassInvolvementMapper                     $classInvolvementMapper
     * @param \Alaress_Date_Format                       $fromDate
     * @param \Alaress_Date_Format                       $toDate
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private IApi $api,
        private array $watchlist,
        private string $externalStaffPortal,
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper,
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
     * @return void
     * @throws DebugBarException
     * @throws Exception
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        $this->log->info('Processing timetables.');

        $this->log->debug(sprintf(
            'Processing lessons between %s and %s',
            $this->fromDate,
            $this->toDate,
        ));

        /* /api/0/TeachingGroups/ByDate is broken and returns no data;
         * We have to use the overly generic /api/0/TeachingGroups/Jurisdiction
         * endpoint instead and manually filter out all the jank we don't need;
         * what a waste
         */
        $pages = $this->api->retrieveForJurisdiction('GET', 'TeachingGroups/Jurisdiction/ByDate', [
            'startDate' => $this->fromDate->getDatabaseDate(),
            'endDate' => $this->toDate->getDatabaseDate()
        ]);

        foreach ($pages as $page) {
            foreach ($page['values'] as $class) {
                $this->processClass($class);
            }
        }
    }


    /**
     * For each class, process the lessons and apply student/staff involvements
     * Ignore lessons that occur outside the [$from, $to] window
     *
     * @param array $class
     * @return void
     * @throws DebugBarException
     * @throws Exception
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function processClass(array $class): void
    {
        foreach ($class['sessions'] as $classInstance) {
            /* Sometimes civica doesn't have a date and/or time for a class instance
             * because civica reasons...they don't seem to know
             */
            if ('' === trim((string)$classInstance['date'])) {
                $this->log->debug(sprintf(
                    'Class instance for %s (%s) missing date: skipping',
                    $class['subjectFullName'],
                    $class['className']
                ));

                continue;
            }

            if ('' === trim((string)$classInstance['startTime'])) {
                $this->log->debug(sprintf(
                    'Class instance for %s (%s) missing start time: skipping',
                    $class['subjectFullName'],
                    $class['className']
                ));

                continue;
            }

            if ('' === trim((string)$classInstance['endTime'])) {
                $this->log->debug(sprintf(
                    'Class instance for %s (%s) missing end time: skipping',
                    $class['subjectFullName'],
                    $class['className']
                ));

                continue;
            }

            /* Do a lookahead to see whether this classInstance is relevant
             * to $externalId or not
             */
            $candidates = [];

            foreach ($class['students'] as $student) {
                $candidates[] = (string)$student['personId'];
            }

            /* If any of the candidates from this class enrolments are on the watchlist
             * Then process the data like we usually do; otherwise skip it
             */
            if (!(new Watchlist())->isAnyOnWatchlist($candidates, $this->watchlist)) {
                continue;
            }

            /* If this particular $classInstance falls outside our desired timeframe, skip it
             */
            $startAt = (new \Alaress_Date_Format(
                sprintf(
                    '%s %s',
                    $classInstance['date'],
                    $classInstance['startTime'],
                ),
                \Alaress_Date_Format::DATABASE_FORMAT
            ));

            $endAt = (new \Alaress_Date_Format(
                sprintf(
                    '%s %s',
                    $classInstance['date'],
                    $classInstance['endTime'],
                ),
                \Alaress_Date_Format::DATABASE_FORMAT
            ));

            /* Event starts in the future; ignore it
             */
            if ($startAt->gt($this->toDate)) {
                $this->log->debug(sprintf(
                    'Class %s (%s) starts too far in the future; skipping',
                    $class['subjectFullName'],
                    $class['className']
                ));

                continue;
            }

            /* Event ended in the past; ignore it
             */
            if ($endAt->lt($this->fromDate)) {
                $this->log->debug(sprintf(
                    'Class %s (%s) started too far in the past; skipping',
                    $class['subjectFullName'],
                    $class['className']
                ));

                continue;
            }

            /* Save sis_event record
             */
            $event = $this->createEvent($class, $classInstance, $startAt, $endAt);

            /* Save sis_class record
             */
            $classEntry = $this->createClassEntry($class, $event);

            /* Save the involvements
             */
            $this->createInvolvements($class, $classInstance, $event, $classEntry);
        }
    }


    /**
     * Create a new SUDS Timetable Event with all the class and lesson pieces
     *
     * @param array $class
     * @param array $classInstance
     * @param \Alaress_Date_Format $startAt
     * @param \Alaress_Date_Format $finishAt
     * @return Model
     * @throws DebugBarException
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createEvent(
        array $class,
        array $classInstance,
        \Alaress_Date_Format $startAt,
        \Alaress_Date_Format $finishAt,
    ): \Schoolbox\External\Event\Model {
        $classCode = (new ClassCode($this->log))->generate(trim((string)$class['className']));
        $day = null;
        $detail = null;

        /* The session id isn't unique per class; it can be shared
         * We can dedupe further by namespacing with the classCode
         */
        $guid = sprintf(
            '%s-%s',
            trim((string)$classInstance['sessionId']),
            $classCode
        );

        $period = null;
        $room = trim((string)$classInstance['room']);
        $title = trim((string)$class['subjectFullName']);

        /* This is pretty optional - this is the sis' equivalent of our luEventType
         * Sometimes it's provided, sometimes it's not - we can record it here if
         * possible. If you can't get it, dw about it
         */
        $type = '';

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
            ->setMetadata((new Metadata\Builder($this->externalStaffPortal))->build($class, $classInstance))
            ->setClassification(Model::CLASSIFICATION_TIMETABLE)
            ->setGuid($guid)
            ->setSource(Factory::SOURCE_NAME);

        /* Save it and remember what we saw in case we need to reuse this later
         */
        $this->log->debug(sprintf(
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
     * Create (or reuse) a ClassEntry corresponding to $event
     *
     * @param array                           $class
     * @param \Schoolbox\External\Event\Model $event
     * @return ClassEntry
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function createClassEntry(array $class, \Schoolbox\External\Event\Model $event): ClassEntry
    {
        if (isset($this->classEntries[$event->getCode()])) {
            $classEntry = $this->classEntries[$event->getCode()];
        } else {
            $classEntry = $this->classEntryMapper->createModel()
                ->setName($class['subjectFullName'])
                ->setCode($class['className']);

            if ($this->perform) {
                $this->classEntryMapper->save($classEntry);
            }

            $this->classEntries[$event->getCode()] = $classEntry;
        }

        return $classEntry;
    }


    /**
     * Save all the involvements for a given $class
     *
     * @param array $class
     * @param array $classInstance
     * @param Model $event
     * @param ClassEntry $classEntry
     * @return void
     * @throws Exception
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createInvolvements(
        array $class,
        array $classInstance,
        \Schoolbox\External\Event\Model $event,
        ClassEntry $classEntry
    ): void {
        /* Save sis_class_user involvements
         * This is the teacher(s)
         */
        if (!\count($classInstance['staff'])) {
            $this->log->debug(sprintf(
                '%s: no staff involved',
                $class['className']
            ));
        } else {
            foreach ($classInstance['staff'] as $employee) {
                $externalId = (string)$employee['personId'];

                /* There isn't a convenient way to identify who $externalId
                 * happens to be, by name...you'll have to do by number for now lol
                 */
                $name = $externalId;

                $this->saveInvolvement($event, $classEntry, $name, $externalId);
            }
        }

        /* Save sis_class_user involvements
         * This is the student(s)
         */
        if (!\count($class['students'])) {
            $this->log->debug(sprintf(
                '%s: no students involved',
                $class['className']
            ));
        } else {
            foreach ($class['students'] as $student) {
                $externalId = (string)$student['personId'];

                /* Decide if this class is relevant for this particular student
                 * The student's startDate and endDate dictates whether this student
                 * is even supposed to be in the class - it appears here, even if
                 * it's obsolete...for civica reasons
                 */
                if (!$this->isValid($student['startDate'], $student['endDate'], $event->getStartAt())) {
                    $this->log->debug(sprintf(
                        'Student %s enrolment in %s (%s) has expired; skipping',
                        $externalId,
                        $classEntry->getName(),
                        $classEntry->getCode()
                    ));

                    continue;
                }

                /* There isn't a convenient way to identify who $externalId
                 * happens to be, by name...you'll have to do by number for now lol
                 */
                $name = $externalId;

                $this->saveEnrolment($event, $classEntry, $name, $externalId);
            }
        }
    }


    /**
     * Decide if the student's enrolment date is valid for the session
     * The student's enrolment might end halfway through a teaching period
     * and we don't want them to appear for classes in which they're not
     * attending anymore.
     *
     * @param string $startDate
     * @param string $endDate
     * @param \Alaress_Date_Format $sessionStart
     * @return bool
     * @throws \InvalidArgumentException
     */
    private function isValid(string $startDate, string $endDate, \Alaress_Date_Format $sessionStart): bool
    {
        /* We actually don't care about times here, just dates
         *
         * We can presume enrolments to be at noon  for the express purpose
         * of checking if an enrolment date is valid for a given session
         *
         * Assume that sessions don't span multiple days, for now - that's a bit
         * weird, to have a multiday "class"
         */
        $enrolmentStart = new \Alaress_Date_Format(sprintf('%s 12:00:00', $startDate), \Alaress_Date_Format::DATABASE_FORMAT);
        $enrolmentEnd = new \Alaress_Date_Format(sprintf('%s 12:00:00', $endDate), \Alaress_Date_Format::DATABASE_FORMAT);

        /* Session date must be within the student's enrolment range
         */
        return $sessionStart->between($enrolmentStart, $enrolmentEnd);
    }


    /**
     * Save staff involvement as an override, which allows each class
     * to have different teachers depending on their associated sis_event
     *
     * @param \Schoolbox\External\Event\Model $event
     * @param ClassEntry                      $classEntry
     * @param string                          $name
     * @param string                          $externalId
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function saveInvolvement(
        \Schoolbox\External\Event\Model $event,
        ClassEntry $classEntry,
        string $name,
        string $externalId
    ): void {
        $this->saveEnrolment($event, $classEntry, $name, $externalId);

        /* We use the Overrides system to specify a particular person's involvement
         * in a particular sis_event instance, rather than being associated with all
         */
        $this->log->debug(sprintf(
            'Creating new class involvement for %s in timetable event %s - %s, %s, %s-%s',
            $externalId,
            $event->getTitle(),
            $event->getDay(),
            $event->getPeriod(),
            $event->getStartAt()->getDatabase(),
            $event->getFinishAt()->getDatabase()
        ));

        /* For specific teaching, execute CLEAR_STAFF to disregard the usual guy
         * and add the guy teaching this particular instance (even though they
         * might end up being the same person sometimes)
         */
        $clearOverride = $this->overridesMapper->createModel()
            ->setEventId($event->getId())
            ->setExternalId(null)
            ->setAction(\Schoolbox\External\Event\Overrides\Model::CLEAR_STAFF)
            ->setSequence(1);

        $involvementOverride = $this->overridesMapper->createModel()
            ->setEventId($event->getId())
            ->setExternalId($externalId)
            ->setAction(\Schoolbox\External\Event\Overrides\Model::ADD)
            ->setSequence(2);

        if ($this->perform) {
            $this->overridesMapper->save($clearOverride);
            $this->overridesMapper->save($involvementOverride);
        }
    }

    /**
     * Save an involvement for user $externalId, whose name is $name,
     * for the sis_event $event
     *
     * @param \Schoolbox\External\Event\Model $event
     * @param ClassEntry                      $classEntry
     * @param string                          $name
     * @param string                          $externalId
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function saveEnrolment(
        \Schoolbox\External\Event\Model $event,
        ClassEntry $classEntry,
        string $name,
        string $externalId
    ): void {
        if (isset($this->classInvolvements[$classEntry->getId()][$externalId])) {
            $this->log->debug(sprintf(
                'Reusing previous class involvement for %s (%s) in timetable event %s - %s, %s, %s-%s',
                $name,
                $externalId,
                $event->getTitle(),
                $event->getDay(),
                $event->getPeriod(),
                $event->getStartAt()->getDatabase(),
                $event->getFinishAt()->getDatabase()
            ));
        } else {
            $classInvolvement = $this->classInvolvementMapper->createModel()
                ->setClass($classEntry)
                ->setExternalId($externalId);

            if ($this->perform) {
                $this->classInvolvementMapper->save($classInvolvement);
            }

            $this->classInvolvements[$classEntry->getId()][$externalId] = true;

            $this->log->debug(sprintf(
                'Creating new class involvement for %s (%s) in timetable event %s - %s, %s, %s-%s',
                $name,
                $externalId,
                $event->getTitle(),
                $event->getDay(),
                $event->getPeriod(),
                $event->getStartAt()->getDatabase(),
                $event->getFinishAt()->getDatabase()
            ));
        }
    }
}
