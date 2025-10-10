<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Example\Base\Endpoint\TimetableCalendar;

use DebugBar\DebugBarException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Connector\Example\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Example\Base\Endpoint\Factory;
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
     * Special event query generator that saves partial rows
     *
     * @var EventHandler
     */
    private EventHandler $eventHandler;


    /**
     * Constructor.
     *
     * @param LoggerInterface $log
     * @param bool $perform
     * @param IApi $api
     * @param Watchlist $watcher
     * @param array $watchlist
     * @param Mapper $eventMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     * @param ClassEntryMapper $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     * @param \DateTimeZone $timezone
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
        private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper,
        private ClassEntryMapper $classEntryMapper,
        private ClassInvolvementMapper $classInvolvementMapper,
        private \DateTimeZone $timezone,
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
     * @throws DebugBarException
     * @throws Exception
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function processClass(array $class): void
    {
        foreach ($class['lessons']['data'] as $classInstance) {
            /* TODO Implement the Watchlist by doing a lookahead
             *  to see if this class should even be made; you might
             *  have to refine this approach to something else if
             *  the sis compels you to do it differently
             */
//            /* Do a lookahead to see whether this classInstance is relevant
//             * to $externalId or not
//             */
//            $candidates = [];
//
//            foreach ($class['students'] as $student) {
//                $candidates[] = (string)$student['personId'];
//            }
//
//            /* If any of the candidates from this class enrolments are on the watchlist
//             * Then process the data like we usually do; otherwise skip it
//             */
//            if (!$this->watcher->isAnyOnWatchlist($candidates, $this->watchlist)) {
//                continue;
//            }


            /* Save sis_event record
             */
            $event = $this->createEvent($class, $classInstance);

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
     * @return Model
     * @throws DebugBarException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    private function createEvent(
        array $class,
        array $classInstance
    ): \Schoolbox\External\Event\Model {
        /* TODO Fill in these vars as usual
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
        if (is_array($classInstance['room'])) {
            $room = "{$classInstance['room']['data']['name']} ({$classInstance['room']['data']['code']}";
        }

        /* Don't forget to check if your data is timezone-sensitive
         * Alaress_Date_Format has some consts which you could use
         */
        $startAt = (new \Alaress_Date_Format(
            $classInstance['start'],
            \Alaress_Date_Format::FORMAT_DATETIME_UTC_NO_MLS,
            new \DateTimeZone('UTC')
        ))->setTimezone($this->timezone);

        $finishAt = (new \Alaress_Date_Format(
            $classInstance['end'],
            \Alaress_Date_Format::FORMAT_DATETIME_UTC_NO_MLS,
            new \DateTimeZone('UTC')
        ))->setTimezone($this->timezone);

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
     * @param array                           $classInstance
     * @param \Schoolbox\External\Event\Model $event
     * @param ClassEntry                      $classEntry
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
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
        if (!\count($class['employees']['data'])) {
            $this->log->debug(sprintf(
                '%s: no staff involved',
                $class['code']
            ));
        } else {
            foreach ($class['employees']['data'] as $employee) {
                $externalId = (string)$employee['mis_id'];

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
                $externalId = (string)$student['mis_id'];

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
            ->setExternalId($coveringTeacher['externalId'])
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
                'Reusing previous class enrolment for %s (%s) in timetable event %s - %s, %s, %s-%s',
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
                'Creating new class enrolment for %s (%s) in timetable event %s - %s, %s, %s-%s',
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
