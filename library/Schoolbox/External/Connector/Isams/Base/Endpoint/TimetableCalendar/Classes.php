<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\TimetableCalendar;

use DebugBar\DebugBarException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Event\Model;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\Mapper\Exception;


/**
 * Convert isams cyclic grid into schedule format and shank in
 * all the humans involved over the given time interval
 */
class Classes implements IStrategy
{
    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private bool $perform;


    /**
     * @var array
     */
    private array $api;


    /**
     * @var \Schoolbox\External\Event\Mapper
     */
    private \Schoolbox\External\Event\Mapper $eventMapper;


    /**
     * @var \Schoolbox\External\Event\Overrides\Mapper
     */
    private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper;


    /**
     * @var ClassEntryMapper
     */
    private ClassEntryMapper $classEntryMapper;


    /**
     * @var ClassInvolvementMapper
     */
    private ClassInvolvementMapper $classInvolvementMapper;


    /**
     * Constructor.
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        array $api,
        \Schoolbox\External\Event\Mapper $eventMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overridesMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,

    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->eventMapper = $eventMapper;
        $this->overridesMapper = $overridesMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
    }


    /**
     * @return void
     */
    public function import(): void
    {
        /* Unroll the cyclic grid into a flat calendar format
         * (this part is pretty quick)
         */
        $timetableDefinition = (new CycleUnroller($this->api))->unroll();

        /* Used to shank info about classes and their humans iteratively
         * because we don't need to load *everything* at the start, just
         * as we go
         */
        $scheduleCompiler = new ScheduleCompiler($this->api, $timetableDefinition);

        /* Shank information about the periods occurring during $timetableDefinition
         */
        $schedules = $scheduleCompiler->findSchedules();

        /* Proceed through each week, day and period;
         * obtain more detailed info as you iteratively proceed
         * (this part so slow we do it separately)
         */
        foreach ($timetableDefinition as $weekId => $weekData) {
            foreach ($weekData['days'] as $dayData) {
                foreach ($dayData['periods'] as $periodId => $periodData) {
                    /* guid prefix is serialized with these pieces:
                     *     isams timetable id
                     *     isams day id
                     *     isams period id
                     */
                    $guidPrefix = sprintf(
                        '%s.%s.%s',
                        $weekId,
                        $dayData['isamsId'],
                        $periodId
                    );

                    /* Some periods don't have anything on; that arg is set
                     * to null in those circumstances
                     */
                    $this->processClass($scheduleCompiler, $guidPrefix, $dayData, $periodData, $schedules[$periodId] ?? null);
                }
            }
        }
    }


    /**
     * Process all the classes that occur on period $periodId
     *
     * @param ScheduleCompiler $scheduleCompiler
     * @param string           $guidPrefix
     * @param array            $dayData
     * @param array            $periodData
     * @param array|null       $classes
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function processClass(
        ScheduleCompiler $scheduleCompiler,
        string $guidPrefix,
        array $dayData,
        array $periodData,
        ?array $classes
    ): void {
        /* Nothing happens on this period; exit early
         */
        if (null === $classes) {
            return;
        }

        /* Process detailed data procedurally, saving on a mega-preload
         * of the timetable struct in its most detailed format
         *
         * * create classEntry
         * * create regular staff involvement
         * * create overrides for subby teachers
         * * create regular student involvements
         * * shank it into suds town
         */
        foreach ($classes['schedule'] as $scheduleId => $classData) {
            $subject = $scheduleCompiler->findSubject($classData['code']);

            /* Save sis_event record
             */
            $event = $this->createEvent($guidPrefix, $dayData, $periodData, $classData, $subject);

            /* Save sis_class record
             */
            $classEntry = $this->createClassEntry($subject, $event);
        }

//
//        /* Save the involvements
//         */
//        $this->createInvolvements($activity, $activityInstance, $enrolments, $staffLookup, $event, $classEntry);
    }


    /**
     * Create a new SUDS Timetable Event with all the class and lesson pieces
     *
     * @param string $guidPrefix
     * @param array  $dayData
     * @param array  $periodData
     * @param array  $classData
     * @param array  $subject
     * @return Model
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    private function createEvent(
        string $guidPrefix,
        array $dayData,
        array $periodData,
        array $classData,
        array $subject
    ): \Schoolbox\External\Event\Model {
        /* Fill in these vars as usual
         */
        $classCode = trim((string)$classData['code']);
        $day = trim((string)$dayData['dayNumber']);
        $detail = null;

        /* Arbitrarily concoct a superkey to represent this instance
         *
         * Overall guid is serialized with these pieces:
         *     isams timetable id
         *     isams day id
         *     isams period id
         *     isams subject id
         *     isams class code
         */
        $guid = sprintf(
            '%s.%s.%s',
            $guidPrefix,
            $subject['subjectId'],
            $classCode,
        );

        $period = trim((string)$periodData['name']);
        $room = trim((string)$classData['room']['name']);
        $title = $classData['code'];

        /* Datetimes for when this class occurs
         */
        $startAt = \Alaress_Date_Format::newFromTimestamp(strtotime($periodData['startAt']));
        $finishAt = \Alaress_Date_Format::newFromTimestamp(strtotime($periodData['finishAt']));

        /* This is pretty optional - this is the sis' equivalent of our luEventType
         * Sometimes it's provided, sometimes it's not - we can record it here if
         * possible. If you can't get it, dw about it
         */
        $type = '';

        /* Try to recycle based on guid
         */
        try {
            $event = $this->eventMapper->findOneByColumn('guid', $guid);
            $logPrefix = 'Reusing';

        } catch (\Schoolbox\Mapper\Exception $e) {
            $event = $this->eventMapper->createModel();
            $logPrefix = 'Creating';
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
            ->setSource(\Schoolbox\External\Connector\Isams\Base\Endpoint\Factory::SOURCE_NAME);

        /* Save it and remember what we saw in case we need to reuse this later
         */
        $this->log->debug(sprintf(
            '%s new timetable event %s (%s) on day %s, period %s, %s-%s',
            $logPrefix,
            $event->getTitle(),
            $event->getCode(),
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
     * @param array $subject
     * @param Model $event
     * @return ClassEntry
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createClassEntry(array $subject, \Schoolbox\External\Event\Model $event): ClassEntry
    {
        /* Name is the same as the Code, in compass
         */
        $name = trim((string)$subject['name']);

        /* Constraints in the schema are case insensitive, unlike
         * these array keys - class codes sometimes appear like
         * "Speech Therapy" and "Speech therapy" so inserting them
         * twice will cause it to pack a sad. The classEntries array
         * treats these as unique whereas the db didn't
         */
        $existingKey = strtolower($event->getCode());

        if (isset($this->classEntries[$existingKey])) {
            $classEntry = $this->classEntries[$existingKey];
        } else {
            $classEntry = $this->classEntryMapper->createModel()
                ->setName($name)
                ->setCode($name);

            if ($this->perform) {
                $this->classEntryMapper->save($classEntry);
            }

            $this->classEntries[strtolower($event->getCode())] = $classEntry;
        }

        return $classEntry;
    }
}
