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
     * @throws DebugBarException
     * @throws Exception
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        $start = microtime(true);

        $this->log->info('Processing timetables.');

        $this->log->debug(sprintf(
            'Processing classes between %s and %s',
            $this->fromDate,
            $this->toDate
        ));

        /* Precompiler
         * Note this has a local cache that remembers an endpoint's outcome
         */
//        $compiler = new PersonCompiler($this->api, self::SCOPE);

        /* Get a list of guys
         */
        $userLookup = (new Users($this->eventMapper))->generate();

        $startDate = $this->fromDate->getDatabaseDate();
        $endDate = $this->toDate->getDatabaseDate();

        /* Start with an enrolments-first philosophy.
         * The list of enrolments informs us which internal_class_ids we care about.
         *
         * If a watchlist contains external ids, only obtain a list of enrolments
         * for each of the external ids (person ids) provided. This limits the amount
         * of classes and meeting times we need to prefetch and remember.
         *
         * Otherwise, don't apply a person_id limiter on the enrolments.
         */
        $enrolmentsLookup = (new Enrolments($this->api, $this->watchlist))->generate();

        /* Once you have all the enrolments, find all the internal_class_id values.
         * Use these internal_class_id to prefilter classes:list to the ones we need.
         *
         * This results in a list of classes that are relevant to the enrolments we prefetched.
         */
        $classesLookup = (new ClassIndex($this->api, $this->watchlist, $enrolmentsLookup))->generate();

        /* Each class needs its meeting times which we can obtain with academics.classes.meeting_times:list
         * and precompile that into each class.
         */


        /* At the end of all this you should have a:
         *   - a lookup of enrolments which tells you a person and the internal_class_ids they're in
         *   - a lookup of classes + their meeting times
         *
         * ...which should allow you to craft the entire timetable for every relevant person
         * without needing api spam to veracross
         */



        foreach ($userLookup as $externalId => $user) {
            /* Only import for specific users
             */
            if (!$this->watcher->isOnWatchlist((string)$externalId, $this->watchlist)) {
                continue;
            }

            $this->log->debug(sprintf(
                'Fetching timetable data for %s %s (%s) - this might take a little while',
                $user['firstname'],
                $user['lastname'],
                $externalId
            ));

//            $timetable = $compiler->compile((string)$externalId, $startDate, $endDate);
//
//            /* A student's timetable is a set of enrolments for their classes
//             */
//            foreach ($timetable as $enrolment) {
//                /* You can use the class_id (this is the Veracross class code)
//                 * as a watchlist param to specifically import this one class.
//                 * Once we find it, we exit this loop - we don't expect it to
//                 * appear again in the classes index. You can use the numeric
//                 * class id instead if that helps
//                 */
////                if (!$this->watcher->isOnWatchlist((string)$enrolment['internal_class_id']['id'], $this->watchlist)) {
////                    continue;
////                }
////                if (!$this->watcher->isOnWatchlist((string)$enrolment['internal_class_id']['class_id'], $this->watchlist)) {
////                    continue;
////                }
//
//                $this->processClass((string)$externalId, $enrolment, $userLookup);
//            }
        }

        $end = microtime(true);

        $this->log->info('');

        $this->log->info(sprintf(
            'Duration: %s seconds.',
            ($end - $start)
        ));
    }


    /**
     * For each class, process the lessons and apply student/staff involvements
     * Ignore lessons that occur outside the [$from, $to] window
     *
     * @param string $externalId
     * @param array  $enrolment
     * @param array  $userLookup
     * @return void
     * @throws DebugBarException
     * @throws Exception
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function processClass(string $externalId, array $enrolment, array $userLookup): void
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
            $this->createInvolvements($externalId, $class, $event, $classEntry, $userLookup);
        }
    }


    /**
     * Create a new SUDS Timetable Event with all the class and lesson pieces
     *
     * @param array $class
     * @param array $classInstance
     * @return Model
     * @throws Exception
     * @throws DebugBarException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createEvent(
        array $class,
        array $classInstance
    ): Model {
        /* Fill in these vars as usual
         */
        $classCode = (new ClassCode($this->log))->generate(trim((string)$class['class_id']));
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

        /* There is an omega bug in Veracross where they send us UTC timestamps
         * but they aren't actually UTC values lol...for example, they send this:
         *
            [start] => 2025-09-02T14:30:00Z
            [end] => 2025-09-02T15:25:00Z

         * ...but that means it should display at 2:30pm-3:25pm in the timetable.
         * We can glitch around this by simply str_replace T and Z out (?!!!?)
         * until they figure out how to repair their api.
         */
//        $startAt = (new \Alaress_Date_Format(
//            $classInstance['start'],
//            \Alaress_Date_Format::FORMAT_DATETIME_UTC_NO_MLS,
//            new \DateTimeZone('UTC')
//        ))->setTimezone($this->timezone);
//
//        $finishAt = (new \Alaress_Date_Format(
//            $classInstance['end'],
//            \Alaress_Date_Format::FORMAT_DATETIME_UTC_NO_MLS,
//            new \DateTimeZone('UTC')
//        ))->setTimezone($this->timezone);

        /* Weird hacky version for now. Revert when veracross repairs their data.
         */
        $tempStart = trim(str_replace(['T', 'Z'], ' ', $classInstance['start']));
        $tempEnd = trim(str_replace(['T', 'Z'], ' ', $classInstance['end']));

        $startAt = (new \Alaress_Date_Format(
            $tempStart,
            \Alaress_Date_Format::DATABASE_DATE_FORMAT,
        ));

        $finishAt = (new \Alaress_Date_Format(
            $tempEnd,
            \Alaress_Date_Format::DATABASE_DATE_FORMAT,
        ));

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
     * @param array      $userLookup
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createInvolvements(
        string $studentExternalId,
        array $class,
        Model $event,
        ClassEntry $classEntry,
        array $userLookup
    ): void {
        /* Save the teacher's involvement
         */
        $teacherExternalId = trim((string)$class['primary_teacher']['id']);
        $name = trim((string)$class['primary_teacher']['name']);

        /* TODO Figure out overrides to put into saveStaffInvolvement
         *  So far, no distinction has yet been made for Overrides to exist
         */
        $this->saveInvolvement($event, $classEntry, $name, $teacherExternalId);

        /* Save the student's enrolment
         *
         * In case you pick a guy that isn't known in the db, you can still proceed
         */
        $name = sprintf(
            '%s %s',
            $userLookup[$studentExternalId]['firstname'] ?? '',
            $userLookup[$studentExternalId]['lastname'] ?? '',
        );

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
        Model $event,
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
