<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Factory;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\IApi;
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
    public const SCOPE = '
        classes:list
        academics.enrollments:list
        academics.classes.meeting_times:list
    ';


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
     * @var array
     */
    private array $watchlist;


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
     * @param array                                      $watchlist
     * @param Mapper                                     $eventMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
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
        array $watchlist,
        \Schoolbox\External\Event\Mapper $eventMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overridesMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        \DateTimeZone $timezone,
        \Alaress_Date_Format $fromDate,
        \Alaress_Date_Format $toDate
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->watchlist = $watchlist;
        $this->eventMapper = $eventMapper;
        $this->overridesMapper = $overridesMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->timezone = $timezone;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }


    /**
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        $this->log->info('Processing timetables.');

        $this->log->debug(sprintf(
            'Processing classes between %s and %s',
            $this->fromDate,
            $this->toDate
        ));

        /* Precompiler
         * Note this has a local cache that remembers an endpoint's outcome
         */
        $compiler = new PersonCompiler($this->api, self::SCOPE);

        /* Get a list of guys
         */
        $userLookup = (new Users($this->eventMapper))->generate();

        /* TODO Parameterise these
         *  For now, this 2016 year has timetabled data. Need a pilot school
         *  for actual data, not manufactured tests
         */
        $startDate = '2016-08-01';
        $endDate = '2016-09-01';

        /* TODO a manual limit, because so slow due to latency
         *  It takes about 30 minutes to process 100 timetables alone
         *  so the average school would take 5 hours at this rate.
         *  This break allows you to leave earlier than that, while
         *  still obtaining something vaguely meaningful
         */
        $i = 0;

        foreach ($userLookup as $externalId => $user) {
            if ($i > 50) {
                break;
            }

            $this->log->debug(sprintf(
                'Fetching timetable data for %s %s (%s) - this might take a little while',
                $user['firstname'],
                $user['lastname'],
                $externalId
            ));

            $timetable = $compiler->compile((string)$externalId, $startDate, $endDate);

            /* A student's timetable is a set of enrolments for their classes
             */
            foreach ($timetable as $enrolment) {
                /* You can use the class id as a watchlist param to specifically
                 * import this one class. Once we find it, we exit this loop -
                 * we don't expect it to appear again in the classes index
                 */
                if (!(new Watchlist())->isOnWatchlist((string)$enrolment['internal_class_id']['id'], $this->watchlist)) {
                    continue;
                }

                $this->processClass((string)$externalId, $enrolment);
            }

            $i++;
        }
    }


    /**
     * For each class, process the lessons and apply student/staff involvements
     * Ignore lessons that occur outside the [$from, $to] window
     *
     * @param string $externalId
     * @param array  $enrolment
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function processClass(string $externalId, array $enrolment): void
    {
        foreach ($enrolment['schedule'] as $classInstance) {
            $class = $enrolment['internal_class_id'];

            /* Save sis_event record
             */
            $event = $this->createEvent($class, $classInstance);

            /* Save sis_class record
             */
            $classEntry = $this->createClassEntry($class, $event);

            /* Save the involvements
             */
            $this->createInvolvements($externalId, $class, $event, $classEntry);
        }
    }


    /**
     * Create a new SUDS Timetable Event with all the class and lesson pieces
     *
     * @param array $class
     * @param array $classInstance
     * @return Model
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    private function createEvent(
        array $class,
        array $classInstance
    ): Model
    {
        /* Fill in these vars as usual
         */
        $classCode = trim((string)$class['class_id']);
        $day = trim((string)$classInstance['day']['name']);
        $detail = null;

        /* Arbitrarily manufacture a guid for now, which represents
         * a class whose instance is happening at a particular time
         */
        $guid = sprintf(
            '%s_%s_%s',
            $class['class_id'],
            $classInstance['start'],
            $classInstance['end']
        );

        $period = null;
        $room = null;
        $title = trim((string)$class['description']);

        /* This is pretty optional - this is the sis' equivalent of our luEventType
         * Sometimes it's provided, sometimes it's not - we can record it here if
         * possible. If you can't get it, dw about it
         */
        $type = '';

        /* Decide where this class happens
         */
        if (is_array($classInstance['room'])) {
            $room = $classInstance['room']['name'];
        }

        /* Convert strings to adf
         *
         * Apply timezone settings to these zulu-time dates
         */
        $this->log->debug(sprintf(
            'Original dates: %s - %s',
            $classInstance['start'],
            $classInstance['end']
        ));

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

        /* Diff. If this is empty, the objects are the same
         * and we don't need to persist it again to the db
         */
        $diff = $this->eventMapper->diff($event, $oldEvent);

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
        if ($this->perform && !empty($diff)) {
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
                ->setName($class['description'])
                ->setCode($class['class_id']);

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
     * @param string     $studentExternalId
     * @param array      $class
     * @param Model      $event
     * @param ClassEntry $classEntry
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createInvolvements(
        string     $studentExternalId,
        array      $class,
        Model      $event,
        ClassEntry $classEntry
    ): void {
        /* Save the teacher's involvement
         */
        $externalId = trim((string)$class['primary_teacher']['id']);
        $name = trim((string)$class['primary_teacher']['name']);

        /* TODO Figure out overrides to put into saveStaffInvolvement
         *  So far, no distinction has yet been made for Overrides to exist
         */
        $this->saveInvolvement($event, $classEntry, $name, $externalId);

        /* Save the student's enrolment
         *
         * We don't have their name conveniently, so for now it can be just ''
         * and you'll have to track people down via their external id instead
         */
        $name = '';

        $this->saveEnrolment($event, $classEntry, $name, $studentExternalId);
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
        Model      $event,
        ClassEntry $classEntry,
        string     $name,
        string     $externalId
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
        Model      $event,
        ClassEntry $classEntry,
        string     $name,
        string     $externalId
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
