<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Wonde\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Connector\ClassCode;
use Schoolbox\External\Connector\Wonde\Base\Endpoint\Factory;
use Schoolbox\External\Connector\Wonde\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Wonde\Base\Endpoint\Lookup\IdToMisId;
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
     * Prevent infinite loop of api calls
     */
    private const MAX_CALLS = 100;


    /**
     * Number of records per page
     * The maximum is an unwritten 200 or so, apparently
     */
    private const NUM_PER_PAGE = 200;


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
     * A set of (wonde internal identifier, sis external identifier) tuples
     * because wonde misrepresents data like that...
     *
     * @var array
     */
    private array $lookup;


    /**
     * Constructor.
     *
     * @param LoggerInterface                            $log
     * @param bool                                       $perform
     * @param IApi                                       $api
     * @param Mapper                                     $eventMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     * @param ClassEntryMapper                           $classEntryMapper
     * @param ClassInvolvementMapper                     $classInvolvementMapper
     * @param \DateTimeZone                              $timezone
     * @param \Alaress_Date_Format                       $fromDate
     * @param \Alaress_Date_Format                       $toDate
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private IApi $api,
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper,
        private ClassEntryMapper $classEntryMapper,
        private ClassInvolvementMapper $classInvolvementMapper,
        private \DateTimeZone $timezone,
        private \Alaress_Date_Format $fromDate,
        private \Alaress_Date_Format $toDate
    ) {
    }


    /**
     * @return void
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        $this->log->info('Processing timetables.');

        $this->log->info('Preloading identifier tuples for all humans (this might take a while)');

        $this->lookup = (new IdToMisId($this->api))->generate();

        $this->log->debug(sprintf(
            'Processing lessons between %s and %s',
            $this->fromDate,
            $this->toDate
        ));

        /* Wonde does things by Class, then Lessons
         * Get all the classes, then process their lessons
         */
        $nextUrl = sprintf(
            'classes?' . implode('&', [
                'page=%s',
                'per_page=%s',
                'has_students=%s',
                'has_lessons=%s',
                'lessons_start_after=%s',
                'lessons_start_before=%s',
                'include=%s',
            ]),
            1,
            self::NUM_PER_PAGE,
            true,
            true,
            $this->fromDate->getDatabaseDate(),
            $this->toDate->getDatabaseDate(),
            implode(',', [
                'employees',
                'lessons',
                'lessons.room',
                'lessons.period',
                'subject',
                'students',
            ])
        );

        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            $classes = $this->api->retrieve('GET', $nextUrl, []);

            foreach ($classes['data'] as $class) {
                $this->processClass($class);
            }

            /* If there's a pagination struct, there's more data to come...
             * But make sure that the 'next' element exists before trying to go there
             */
            $nextUrl = false;

            if (isset($classes['meta']['pagination'])) {
                $nextUrl = $classes['meta']['pagination']['next'] ?? false;
            }
        }
    }


    /**
     * For each class, process the lessons and apply student/staff involvements
     * Ignore lessons that occur outside the [$from, $to] window
     *
     * @param array $class
     * @return void
     * @throws Exception
     * @throws \DateInvalidTimeZoneException
     */
    private function processClass(array $class): void
    {
        /* $class might actually have a null $class['code'], for wonde reasons
         *
         * For pcschools/pilot school, we need to derive a class code from other fields;
         * se the first non-null of $class['code'] and $class['mis_id']
         *
         * Every other school should presume usage of $class['code']
         */
        $class['code'] = (new ClassCode($this->log))->generate($class['code'] ?? $class['mis_id']);

        foreach ($class['lessons']['data'] as $lesson) {
            /* Decide if we need to process this lesson
             * Outside the window? Skip it
             *
             * Wonde gives you this data struct for dates
             *
             * Convert their UTC timezone into our schoolbox instance's timezone
             * then save it
             *
                [start_at] => Array
                    (
                        [date] => 2022-03-28 00:55:00.000000
                        [timezone_type] => 3
                        [timezone] => UTC
                    )
            */
            $startAt = (new \Alaress_Date_Format(explode('.', $lesson['start_at']['date'])[0], \Alaress_Date_Format::DATABASE_FORMAT, new \DateTimeZone($lesson['start_at']['timezone'])))
                ->setTimezone($this->timezone);

            $finishAt = (new \Alaress_Date_Format(explode('.', $lesson['end_at']['date'])[0], \Alaress_Date_Format::DATABASE_FORMAT, new \DateTimeZone($lesson['end_at']['timezone'])))
                ->setTimezone($this->timezone);

            $this->log->info(sprintf(
                '%s %s on %s',
                $class['name'],
                $class['code'],
                $startAt->getDatabase()
            ));

            /* Starts too far in the past
             */
            if ($startAt->lt($this->fromDate)) {
                $this->log->debug(sprintf(
                    '%s %s (%s %s - %s %s) too far in the past; skipping',
                    $class['name'],
                    $class['code'],
                    $lesson['start_at']['date'],
                    $lesson['start_at']['timezone'],
                    $lesson['end_at']['date'],
                    $lesson['end_at']['timezone'],
                ));

                continue;
            }

            /* Starts too far in the future
             */
            if ($startAt->gt($this->toDate)) {
                $this->log->debug(sprintf(
                    '%s %s (%s %s - %s %s) too far in the future; skipping',
                    $class['name'],
                    $class['code'],
                    $lesson['start_at']['date'],
                    $lesson['start_at']['timezone'],
                    $lesson['end_at']['date'],
                    $lesson['end_at']['timezone'],
                ));

                continue;
            }

            /* Class has no code; ignore this class
             * Schoolbox has no way of working with an external class that has no code.
             *
             * I'm not entirely sure how prevalent this setup might be. I haven't encountered
             * a school which had classes sans code; those would be considered regular events
             * to me. I haven't seen inside wonde to decide if that's even technically possible
             * in their software, so this could be a result of bad data or other weird stuff.
             *
             * Re-address upon first encounter of a legitimate class that has no code.
            */
            if ('' === trim((string)$class['code'])) {
                $this->log->warning(sprintf(
                    'Class %s has empty class code - skipping',
                    $class['name'],
                ));

                continue;
            }

            /* Save sis_event record
             */
            $event = $this->createEvent($class, $lesson, $startAt, $finishAt);

            /* Save sis_class record
             */
            $classEntry = $this->createClassEntry($class, $event);

            /* Save the involvements
             */
            $this->createInvolvements($class, $lesson, $event, $classEntry);
        }
    }


    /**
     * Create a new SUDS Timetable Event with all the class and lesson pieces
     *
     * @param array                $class
     * @param array                $lesson
     * @param \Alaress_Date_Format $startAt
     * @param \Alaress_Date_Format $finishAt
     * @return \Schoolbox\External\Event\Model
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function createEvent(
        array $class,
        array $lesson,
        \Alaress_Date_Format $startAt,
        \Alaress_Date_Format $finishAt
    ): \Schoolbox\External\Event\Model {
        /* Fill in these vars as usual
         */
        $classCode = trim((string)$class['code']);
        $day = trim((string)$lesson['period']['data']['day']);

        /* No descriptions available.
         */
        $detail = null;

        /* Arbitrarily concoct a superkey to represent this instance
         */
        $guid = trim((string)$lesson['id']);

        /* The human-readable name of the period
         */
        $period = trim((string)$lesson['period']['data']['name']);

        /* Try to recycle a previous instance
         */
        $room = null;

        /* Rooms are optional and come with two data points - name and code
         * Example wonde data had a meaningful name in the $code e.g. '3a HomeRoom'
         * and a less meaningful name in the $name e.g. '401'
         */
        if (is_array($lesson['room'])) {
            $room = "{$lesson['room']['data']['name']} ({$lesson['room']['data']['code']}";
        }

        $title = trim((string)$class['name']);

        /* This is pretty optional - this is the sis' equivalent of our luEventType
         * Sometimes it's provided, sometimes it's not - we can record it here if
         * possible. If you can't get it, dw about it
         */
        $type = '';

        /* Try to recycle based on guid
         */
        try {
            $event = $this->eventMapper->findOneByColumn('guid', $guid);
            $logPrefix = 'Reusing existing';
        } catch (\Schoolbox\Mapper\Exception $e) {
            $event = $this->eventMapper->createModel();
            $logPrefix = 'Creating new';
        }

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
            ->setClassification(\Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE)
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

        if ($this->perform) {
            $this->eventMapper->save($event);
        }

        return $event;
    }


    /**
     * Create (or reuse) a ClassEntry corresponding to $event
     *
     * @param array $class
     * @param Model $event
     * @return ClassEntry
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createClassEntry(
        array $class,
        \Schoolbox\External\Event\Model $event
    ): ClassEntry {
        $name = trim((string)$class['name']);
        $code = trim((string)$class['code']);

        if (isset($this->classEntries[$event->getCode()])) {
            $classEntry = $this->classEntries[$event->getCode()];
        } else {
            $classEntry = $this->classEntryMapper->createModel()
                ->setName($name)
                ->setCode($code);

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
     * @param array $lesson
     * @param Model $event
     * @param ClassEntry $classEntry
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createInvolvements(
        array $class,
        array $lesson,
        \Schoolbox\External\Event\Model $event,
        ClassEntry $classEntry
    ): void {
        if (!\count($class['employees']['data'])) {
            /* There aren't any teachers enrolled in the subject.
             * Note that this isn't necessarily the people conducting the class,
             * just the guys who have something to do with it
             */
            $this->log->debug(sprintf(
                '%s: no staff enrolled for this class',
                $class['code']
            ));
        } else {
            /* Save a staff member's regular enrolment in the class;
             * These guys all have something to do with this class.
             */
            foreach ($class['employees']['data'] as $employee) {
                $externalId = $employee['mis_id'];

                $name = sprintf(
                    '%s %s %s',
                    $employee['title'],
                    $employee['forename'],
                    $employee['surname']
                );

                $this->log->debug(sprintf(
                    '%s: created enrolment for %s',
                    $externalId,
                    $class['code']
                ));

                $this->saveEnrolment($event, $classEntry, $name, $externalId);
            }
        }

        if ('' === trim((string)($lesson['employee'] ?? null))) {
            /* This is the employee currently conducting the lesson;
             * a lesson is an "instance of a class" so to speak
             * Without a teacher actually doing any teaching, warn and skip
             */
            $this->log->debug(sprintf(
                '%s: no staff involved for an instance of this class',
                $class['code']
            ));
        } else {
            /* Save a staff member's involvement for this instance of a class
             * This person teaches this particular instance of the class.
             * Sadly wonde won't tell you who this person is; just their id
             * (and even that id isn't the one that schools use...)
             */
            $wondeIdentifier = trim((string)($lesson['employee'] ?? null));

            $externalId = $this->lookup[$wondeIdentifier] ?? null;

            if (null === $externalId) {
                $this->log->debug(sprintf(
                    '%s: unknown Wonde internal identifier %s; skipping',
                    $class['code'],
                    $wondeIdentifier
                ));

                return;
            }

            /* Otherwise continue like we always tried to do...we don't have a
             * $name here so just use externalId in its place. You could update
             * the IdToMisId lookup to also have names, though, if that helps
             */
            $name = $externalId;

            $this->saveInvolvement($event, $classEntry, $name, $externalId);

            $this->log->debug(sprintf(
                '%s: created involvement override for %s for an instance of this class',
                $class['code'],
                $externalId
            ));
        }

        /* Save sis_class_user involvements
         * This is the student(s)
         */
        if (!\count($class['students']['data'])) {
            $this->log->debug(sprintf(
                '%s: no students involved',
                $class['code']
            ));
        } else {
            foreach ($class['students']['data'] as $student) {
                $externalId = $student['mis_id'];

                $name = sprintf(
                    '%s %s',
                    $student['forename'],
                    $student['surname']
                );

                $this->saveEnrolment($event, $classEntry, $name, $externalId);
            }
        }
    }


    /**
     * Save staff involvement as an override, which allows each class
     * to have different teachers depending on their associated sis_event
     *
     * @param Model      $event
     * @param ClassEntry $classEntry
     * @param string     $name
     * @param string     $externalId
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function saveInvolvement(
        Model $event,
        ClassEntry $classEntry,
        string $name,
        string $externalId
    ): void {
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
     * @param Model      $event
     * @param ClassEntry $classEntry
     * @param string     $name
     * @param string     $externalId
     * @return void
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
