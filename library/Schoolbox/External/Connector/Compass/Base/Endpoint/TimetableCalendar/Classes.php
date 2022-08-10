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
use Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar\Lookup\Attendees;
use Schoolbox\External\Connector\FirstMeaningfulValue;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Import all the classes for a student, saving them as sis_event records
 * with the 'timetable' classification
 */
class Classes implements IStrategy
{
    /**
     * RunningStatus determines whether an activity and/or its instance is actually running
     * 0 is cancelled; 1 is running; 2 is draft.
     *
     * https://wyndhamchristiancollege-integration-vic.compass.education/ApiDocs/Documentation/index.html#api-Schedule-GetSchedule
     */
    private const IS_RUNNING = 1;


    /**
     * Number of days into the future whose Activity Instances we import,
     * used to prevent importing billions of events from a distant future
     * that might not really have much relevance to today
     *
     * Defaults to 6 months but we can tune that later.
     */
    private const FUTURE_THRESHOLD = 30 * 12;


    /**
     * Number of days into the past whose Activity Instances we import.
     * This is used to import past events so you can see what you did last week.
     *
     * Otherwise at the end of the current day you wouldn't be able to see the
     * events of that day if UPS ran and deleted those events because they were
     * just "in the past"
     */
    private const PAST_THRESHOLD = 7;


    /**
     * We only import certain activityType values, as described in
     * https://wyndhamchristiancollege-integration-vic.compass.education/ApiDocs/Documentation/index.html#api-Schedule-GetSchedule
     */
    private const ACTIVITY_TYPES = [
        1 => [
            'name' => 'StandardClass',
            'allowed' => true
        ],
        2 => [
            'name' => 'Event',
            'allowed' => true
        ],
        3 => [
            'name' => 'Meeting',
            'allowed' => true
        ],
        4 => [
            'name' => 'Assembly',
            'allowed' => true
        ],
        5 => [
            'name' => 'Generic',
            'allowed' => true
        ],
        6 => [
            'name' => 'Duty',
            'allowed' => true
        ],
        7 => [
            'name' => 'CalendarItem',
            'allowed' => false
        ],
        8 => [
            'name' => 'GenericGroup',
            'allowed' => false
        ],
        9 => [
            'name' => 'ProfessionalDevelopment',
            'allowed' => false
        ],
        10 => [
            'name' => 'LearningTask',
            'allowed' => false
        ],
    ];


    /**
     * This activityType can use getStandardClassDetails to fetch the
     * students and teachers involved (enrolled) in this class
     *
     * All other event types will 500 Internal Server Error if you try
     * You have to fetch those involvements in another way (Attendees)
     */
    private const ACADEMIC_ACTIVITY_TYPE = 1;


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
     * @var string
     */
    private string $lBound;


    /**
     * @var string
     */
    private string $uBound;


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
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     * @param ClassEntryMapper                           $classEntryMapper
     * @param ClassInvolvementMapper                     $classInvolvementMapper
     * @param string                                     $fromDate
     * @param string                                     $toDate
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        IApi $api,
        \Schoolbox\External\Event\Mapper $eventMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overridesMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        string $fromDate,
        string $toDate
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->eventMapper = $eventMapper;
        $this->overridesMapper = $overridesMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;

        /* Data is obtained daily; remember lower and upper bound values
         * for api calls. It needs to be in a specific format so might
         * as well remember that
         */
        $this->lBound = $fromDate;
        $this->uBound = $toDate;
    }


    /**
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        $this->log->info('Fetching Staff Lookup, Academic Groups, Enrolments, Schedules and their Activity Instances...');

        /* Announce window
         */
        $this->log->info(sprintf(
            'Fetching Timetable Events between %s and %s',
            \Alaress_Date_Format::now()->addDays(-1 * self::PAST_THRESHOLD)->getDatabase(),
            \Alaress_Date_Format::now()->addDays(self::FUTURE_THRESHOLD)->getDatabase(),
        ));

        /* Generate a list of staff members
         */
        $this->log->debug('Preloading staff compassPersonIds for non-academic classes...');
        $staffLookup = (new Lookup\Staff($this->api))->generate($this->lBound, $this->uBound);
        $attendeesLookup = new Attendees($this->log, $staffLookup);

        /* We'll need this for later
         */
        $now = \Alaress_Date_Format::now();
        $enrolmentsLookup = new Lookup\Enrolments($this->log, $this->api);

        /* Get a list of Schedules to consume
         */
        $academicGroupSchedules = (new Lookup\Schedules($this->log, $this->api))->generate();

        $this->log->info('Mining all the Schedules for their Activity Instances...');

        /* Quartic n^4 complexity, but I can't think of a convenient way to improve this
         */
        $watchlist = new Watchlist();

        foreach ($academicGroupSchedules as $academicGroupCode => $schedules) {
            foreach ($schedules['schedules'] as $schedule) {
                foreach ($schedule['activities'] as $activity) {
                    $activityType = (int)$activity['activityType'];

                    if ($watchlist->isOnWatchlist($activity['name'], ['8HEAC'])) {
//                        print_r($activity['name']);
//                        print_r($watchlist->translate($activity['instances'], $staffLookup));
                    } else {
                        continue;
                    }

                    /* We only care about certain activities.
                     *
                     * 1. Activity must have one of the allowed activityTypes - we skip certain
                     * activityType classes
                     *
                     * 2. Activity must have a runningStatus 1. 0 is cancelled and 1 is draft,
                     * neither of which we really would care about
                     *
                     * 3. There have to be active instances to import; otherwise no use
                     *
                     * 4. Sometimes trying to look up enrolment data for non-enrolment events
                     * occurs because there's busted data in compass. Schools can put things in
                     * the wrong place. In that case it retries and dies
                     */

                    /* Unknown activity type
                     */
                    if (!isset(self::ACTIVITY_TYPES[$activityType])) {
                        $this->log->debug(sprintf(
                            'Activity %s starting on %s has an unknown activityType %s; skipping',
                            $activity['name'],
                            $activity['start'],
                            $activityType
                        ));

                        continue;
                    }

                    /* Only import specific Activity Types
                     */
                    if (!self::ACTIVITY_TYPES[$activityType]['allowed']) {
                        $this->log->debug(sprintf(
                            'Activity %s starting on %s is a disallowed %s activityType; skipping',
                            $activity['name'],
                            $activity['start'],
                            self::ACTIVITY_TYPES[$activityType]['name']
                        ));

                        continue;
                    }

                    /* Has to be runningStatus 1
                     */
                    if (self::IS_RUNNING !== (int)$activity['runningStatus']) {
                        $this->log->debug(sprintf(
                            'Activity %s starting on %s is not running; skipping',
                            $activity['name'],
                            $activity['start']
                        ));

                        continue;
                    }

                    /* If there aren't any instances to worry about, skip it
                     */
                    if (!\count($activity['instances'])) {
                        $this->log->debug(sprintf(
                            'Activity %s starting on %s has no instances; skipping',
                            $activity['name'],
                            $activity['start']
                        ));

                        continue;
                    }

                    /* Get a list of student external ids, and a list of teacher external ids
                     * then create schoolbox events and their relationships
                     */
                    if (self::ACADEMIC_ACTIVITY_TYPE === $activityType) {
                        try {
                            $enrolments = $enrolmentsLookup->generate($activity['name'], (string)$academicGroupCode);
                        } catch (\RuntimeException $e) {
                            $this->log->warning($e->getMessage());

                            /* Sometimes this randomly dies:
                             *
                             * Bad endpoint schedule/v1/GetStandardClassDetails: 500 Internal Server Error after 3  [   ]
                             * retries. Payload: {"activityCode":"AM Bus","academicGroupCode":"2021"}
                             *
                             * Just skip it and keep going
                             */
                            continue;
                        }
                    } else {
                        /* Find them via attendees struct
                         */
                        $enrolments = $attendeesLookup->generate($activity);
                    }

                    $this->log->info(sprintf(
                        'Schedule %s: fetched enrolments for activity %s starting on %s: %s students',
                        $schedule['name'],
                        $activity['name'],
                        $activity['start'],
                        \count($enrolments['students'])
                    ));

                    /* Combine sessions and instances
                     */
                    $combinedInstances = (new RecombineSessions())->recombine($activity['instances'], $enrolments['sessions']);

                    /* These are instances of classes that occur throughout the year/timetable cycle
                     * We only care about a certain subset of these instances.
                     *
                     * 1. The only activity instances that matter are runningStatus 1. 0 is cancelled
                     * and 1 is draft, neither of which we really would care about
                     *
                     * 2. The instance has to be occurring either today, or in the future. Not sure
                     * there is much value importing things that happened yonks ago
                     *
                     * 3. If it's in the future, it should be within the next FUTURE_THRESHOLD months,
                     * otherwise we're going to be importing a boatload of data we might not need.
                     * I recognise that forward planning might be a thing, but still
                     */
                    foreach ($combinedInstances as $activityInstance) {
                        /* Check if this instance is running
                         * Multiple levels of investigation required.
                         * This is an obscure logic path that is extremely poorly documented
                         *
                         * If runningStatus is 0, it might have been cancelled only for
                         * the original teacher. If that's the case, dig deeper to find
                         * out for sure - it might NOT be cancelled for the replacement
                         */
                        if (self::IS_RUNNING !== (int)$activityInstance['runningStatus']) {
                            /* Replacement teachers can be found inside $activityInstance[attendees]
                             * in a node called coveringCompassPersonId. Check that there are
                             * meaningful contents inside the attendees node...
                             */
                            if (\count($activityInstance['attendees'])) {
                                /* Then compare the 0th element's contents
                                 * (I haven't seen it contain more than one element)
                                 */
                                if ('' === trim((string)$activityInstance['attendees'][0]['coveringCompassPersonId'])) {
                                    $this->log->debug(sprintf(
                                        'Activity %s Instance on %s is not running; skipping',
                                        $activity['name'],
                                        $activityInstance['start']
                                    ));

                                    continue;
                                }
                            }
                        }

                        /* Must start today, or in the future
                         */
                        $start = \Alaress_Date_Format::newFromTimestamp(strtotime($activityInstance['start']));

                        /* Fetch PAST_THRESHOLD days into the past, so people can see where they've been
                         */
                        if ($start->isPast() && $start->diffInDays($now) > self::PAST_THRESHOLD) {
                            $this->log->debug(sprintf(
                                'Activity %s Instance on %s is too far in the past; skipping',
                                $activity['name'],
                                $activityInstance['start']
                            ));

                            continue;
                        }

                        /* Don't fetch too many from the future
                         */
                        if ($start->isFuture() && $start->diffInDays($now) > self::FUTURE_THRESHOLD) {
                            $this->log->debug(sprintf(
                                'Activity %s Instance on %s is too far into the future; skipping',
                                $activity['name'],
                                $activityInstance['start']
                            ));

                            continue;
                        }

                        /* Start manufacturing events and relationships for each person
                         * in $enrolments, then save it
                         */
                        $this->log->info(sprintf(
                            'Manufacturing event for Activity %s Instance on %s which originated in schedule %s; using import identifier %s as folder code',
                            $activity['name'],
                            $activityInstance['start'],
                            $schedule['name'],
                            $activity['name']
                        ));

                        $this->processClass($activity, $activityInstance, $enrolments, $staffLookup);
                    }
                }
            }
        }
    }


    /**
     * For each class, process the lessons and apply student/staff involvements
     * Ignore lessons that occur outside the [$from, $to] window
     *
     * @param array $activity
     * @param array $activityInstance
     * @param array $enrolments
     * @param array $staffLookup
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function processClass(
        array $activity,
        array $activityInstance,
        array $enrolments,
        array $staffLookup
    ): void {

        /* Save sis_event record
         */
        $event = $this->createEvent($activity, $activityInstance);

        /* Save sis_class record
         */
        $classEntry = $this->createClassEntry($activity, $event);

        /* Save the involvements
         */
        $this->createInvolvements($activity, $activityInstance, $enrolments, $staffLookup, $event, $classEntry);
    }


    /**
     * Create a new SUDS Timetable Event with all the class and lesson pieces
     *
     * @param array $activity
     * @param array $activityInstance
     * @return \Schoolbox\External\Event\Model
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function createEvent(
        array $activity,
        array $activityInstance
    ): \Schoolbox\External\Event\Model {

        /* Name is the same as the Code, in compass
         */
        $activityName = trim((string)$activity['name']);

        /* Fill in these vars as usual
         */
        $classCode = $activityName;
        $day = null;
        $detail = null;

        /* Arbitrarily concoct a superkey to represent this activity instance
         */
        $guid = "{$activity['activityId']}.{$activity['importIdentifier']}.{$activityInstance['start']}";

        $period = null;
        $room = trim((string)$activityInstance['location']);
        $title = $activityName;

        /* Datetimes for when this class occurs
         */
        $startAt = \Alaress_Date_Format::newFromTimestamp(strtotime($activityInstance['start']));
        $finishAt = \Alaress_Date_Format::newFromTimestamp(strtotime($activityInstance['finish']));

        /* This is pretty optional - this is the sis' equivalent of our luEventType
         * Sometimes it's provided, sometimes it's not - we can record it here if
         * possible. If you can't get it, dw about it
         */
        $type = '';

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
     * @param array                           $activity
     * @param \Schoolbox\External\Event\Model $event
     * @return ClassEntry
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function createClassEntry(array $activity, \Schoolbox\External\Event\Model $event): ClassEntry
    {
        /* Name is the same as the Code, in compass
         */
        $activityName = trim((string)$activity['name']);

        if (isset($this->classEntries[$event->getCode()])) {
            $classEntry = $this->classEntries[$event->getCode()];
        } else {
            $classEntry = $this->classEntryMapper->createModel()
                ->setName($activityName)
                ->setCode($activityName);

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
     * @param array                           $activity
     * @param array                           $activityInstance
     * @param array                           $enrolments
     * @param array                           $staffLookup
     * @param \Schoolbox\External\Event\Model $event
     * @param ClassEntry                      $classEntry
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function createInvolvements(
        array $activity,
        array $activityInstance,
        array $enrolments,
        array $staffLookup,
        \Schoolbox\External\Event\Model $event,
        ClassEntry $classEntry
    ): void {
        /* Name is the same as the Code, in compass
         */
        $activityName = trim((string)$activity['name']);

        /* Each instance potentially has a replacement teacher
         * Replacement teachers take precedence over the usual teacher
         *
         * Instance teachers are defined in this order or precedence,
         * because Compass reasons:
         *
         * instance[attendees][coveringCompassPersonId]
         *     these are additional teacher ids on top of the next three
         *     and seem to be some kind of rostered substitute from edval
         * instance[attendees][compassPersonId]
         *     this is seemingly the same as at least one of the following fields
         * instance[coveringCompassPersonId]
         * instance[managingCompassPersonId]
         *     this is the "usual guy" teaching the class
         * activity[managerCompassPersonId]
         *     this is also the "usual guy" teaching the class, but with a different
         *     array key (for shiggles, presumably)
         * leadTeacherCode
         *     There's a sixth place - it's found in Standard Class Details but we
         *     were told by Nick Swan to avoid using it in favour of the five aforementioned
         *     (I recognise that's hearsay, but it also happens to be true)
         *
         * Use a local cache because we need to look them up with yet another
         * api call to find their external id, so we can trade space for time
         */
        $teacherIds = [];

        foreach ($activityInstance['attendees'] as $attendeeTeacher) {
            $teacherIds[] = $attendeeTeacher['coveringCompassPersonId'];
            $teacherIds[] = $attendeeTeacher['compassPersonId'];
        }

        $teacherIds[] = $activityInstance['coveringCompassPersonId'];
        $teacherIds[] = $activityInstance['managingCompassPersonId'];
        $teacherIds[] = $activity['managerCompassPersonId'];

        /* Some where amongst this molasses of data is at least one teacher
         * who is teaching this class. Disgusting.
         */
        $teacherCompassIds = (new FirstMeaningfulValue())->find($teacherIds);

        /* No identifiable teachers in any of the fields
         * That is a bit unorthodox, but maybe possible in compass
         *
         * Probably never happens, so if it does, is extremely weird
         * Warn the user
         */
        if ('' === $teacherCompassIds) {
            $this->log->warning(sprintf(
                '%s: no staff involved',
                $activityName
            ));
        } else if (!isset($staffLookup[$teacherCompassIds])) {
            /* If we can't figure out who $teacherCompassIds is,
             * warn the user - that probably shouldn't happen
             * and might be a problem in compass
             */
            $this->log->warning(sprintf(
                '%s: unknown staff involved with compass identifier %s',
                $activityName,
                $teacherCompassIds
            ));
        } else {
            /* Otherwise there's always at least one guy
             * and we know who it is - carry onwards
             */
            $teacher = $staffLookup[$teacherCompassIds];

            /* FIXME Multiple teachers might exist here
             *  It might be possible that $teacherCompassIds is a comma-separated string
             *  of multiple compass person identifiers. I haven't seen it in practice yet but
             *  Compass guy said it *might be possible*. If that's the case, explode on ',' and
             *  then process each person individually
             *
             * foreach ($ids as $id) {
             *     saveStaffInvolvement($staffLookup[$id])
             * }
             */

            $this->log->debug(sprintf(
                'Creating new Override record for Activity %s Instance on %s for %s (compassId %s)',
                $activityName,
                $activityInstance['start'],
                $teacher['externalId'],
                $teacherCompassIds
            ));

            $this->saveStaffInvolvement($teacher, $event);
        }

        /* Save sis_class_user involvements
         * This is the student(s) and they're associated with all instances
         */
        if (!\count($enrolments['students'])) {
            $this->log->debug(sprintf(
                '%s: no students involved',
                $activityName
            ));
        } else {
            foreach ($enrolments['students'] as $externalId) {
                $name = sprintf(
                    'Creating new ClassInvolvement record for Activity %s Instance on %s for %s',
                    $activityName,
                    $activityInstance['start'],
                    $externalId
                );

                $this->saveStudentInvolvement($event, $classEntry, $name, $externalId);
            }
        }
    }


    /**
     * Save staff involvement as an override, which allows each class
     * to have different teachers depending on their associated sis_event
     *
     * @param array                           $teacher
     * @param \Schoolbox\External\Event\Model $event
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function saveStaffInvolvement(
        array $teacher,
        \Schoolbox\External\Event\Model $event
    ): void {
        /* We use the Overrides system to specify a particular person's involvement
         * in a particular sis_event instance, rather than being associated with all
         */
        $override = $this->overridesMapper->createModel()
            ->setEventId($event->getId())
            ->setExternalId($teacher['externalId'])
            ->setAction(\Schoolbox\External\Event\Overrides\Model::ADD)
            ->setSequence(1);

        if ($this->perform) {
            $this->overridesMapper->save($override);
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
    private function saveStudentInvolvement(
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
