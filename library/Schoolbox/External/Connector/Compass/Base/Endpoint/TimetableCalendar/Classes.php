<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Connector\Compass\Base\Endpoint\IApi;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;

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
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private bool $perform;


    /**
     * @var IApi
     */
    private IApi $api;


    /**
     * @var \Schoolbox\External\Event\Mapper
     */
    private \Schoolbox\External\Event\Mapper $eventMapper;


    /**
     * @var ClassEntryMapper
     */
    private ClassEntryMapper $classEntryMapper;


    /**
     * @var ClassInvolvementMapper
     */
    private ClassInvolvementMapper $classInvolvementMapper;


    /**
     * @var \DateTimeZone
     */
    private \DateTimeZone $timezone;


    /**
     * @var \Alaress_Date_Format
     */
    private \Alaress_Date_Format $fromDate;


    /**
     * @var \Alaress_Date_Format
     */
    private \Alaress_Date_Format $toDate;


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
     * Constructor.
     *
     * @param LoggerInterface                            $log
     * @param bool                                       $perform
     * @param IApi                                       $api
     * @param Mapper                                     $eventMapper
     * @param ClassEntryMapper                           $classEntryMapper
     * @param ClassInvolvementMapper                     $classInvolvementMapper
     * @param \DateTimeZone                              $timezone
     * @param \Alaress_Date_Format                       $fromDate
     * @param \Alaress_Date_Format                       $toDate
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        IApi $api,
        \Schoolbox\External\Event\Mapper $eventMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        \DateTimeZone $timezone,
        \Alaress_Date_Format $fromDate,
        \Alaress_Date_Format $toDate
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->eventMapper = $eventMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->timezone = $timezone;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
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

        $this->log->debug('Preloading student involvements for all the classes.');

        $this->log->debug(sprintf(
            'Processing lessons between %s and %s',
            $this->fromDate,
            $this->toDate
        ));

        /* Hit the endpoint to get all the classes as best you can
         */
        $nextUrl = '';

        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            $classes = [];

            foreach ($classes as $class) {
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
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function processClass(array $class)
    {
        foreach ($class['lessons']['data'] as $lesson) {
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
        $classCode = null;
        $day = null;
        $detail = null;
        $guid = null;
        $period = null;
        $room = null;
        $title = null;

        /* This is pretty optional - this is the sis' equivalent of our luEventType
         * Sometimes it's provided, sometimes it's not - we can record it here if
         * possible. If you can't get it, dw about it
         */
        $type = '';

        /* Decide where this class happens
         */
        if (is_array($lesson['room'])) {
            $room = "{$lesson['room']['data']['name']} ({$lesson['room']['data']['code']}";
        }

        /* Cram in everything else
         */
        $event = $this->eventMapper->createModel()
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
            ->setSource(Prepare::EVENT_IMPORT_IDENTIFIER);

        /* Save it and remember what we saw in case we need to reuse this later
         */
        $this->log->debug(sprintf(
            'Creating new timetable event %s - %s, %s, %s-%s',
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
     * Save all the involvements for a given $class
     *
     * @param array                           $class
     * @param array                           $lesson
     * @param \Schoolbox\External\Event\Model $event
     * @param ClassEntry                      $classEntry
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function createInvolvements(
        array $class,
        array $lesson,
        \Schoolbox\External\Event\Model $event,
        ClassEntry $classEntry
    ): void {
        /* Save sis_class_user involvements
         * This is the teacher(s)
         */
        if (!\count($class['employees']['data'])) {
            $this->log->debug(sprintf(
                '%s: no staff involved',
                $class['code']
            ));
        } else {
            foreach ($class['employees']['data'] as $employee) {
                $externalId = $employee['mis_id'];

                $name = sprintf(
                    '%s %s %s',
                    $employee['title'],
                    $employee['forename'],
                    $employee['surname']
                );

                $this->saveInvolvement($event, $classEntry, $name, $externalId);
            }
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

                $this->saveInvolvement($event, $classEntry, $name, $externalId);
            }
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
    private function saveInvolvement(
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
