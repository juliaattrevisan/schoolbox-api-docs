<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\Calendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Compass\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar\Lookup\Attendees;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Event\Model;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\Mapper\Exception;

/**
 * Import all the non-academic events, saving them as sis_event records
 * with the 'timetable' classification
 */
class NonAcademicEvents implements IStrategy
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
    private const PAST_THRESHOLD = 14;


    /**
     * We only import certain activityType values, as described in
     * https://wyndhamchristiancollege-integration-vic.compass.education/ApiDocs/Documentation/index.html#api-Schedule-GetSchedule
     */
    private const ACTIVITY_TYPES = [
        1 => [
            'name' => 'StandardClass',
            'allowed' => false
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
            'allowed' => false
        ],
        5 => [
            'name' => 'Generic',
            'allowed' => true
        ],
        6 => [
            'name' => 'Duty',
            'allowed' => false
        ],
        7 => [
            'name' => 'CalendarItem',
            'allowed' => true
        ],
        8 => [
            'name' => 'GenericGroup',
            'allowed' => true
        ],
        9 => [
            'name' => 'ProfessionalDevelopment',
            'allowed' => true
        ],
        10 => [
            'name' => 'LearningTask',
            'allowed' => true
        ],
    ];


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
     * @var string
     */
    private string $lBound;


    /**
     * @var string
     */
    private string $uBound;


    /**
     * Constructor.
     *
     * @param LoggerInterface                            $log
     * @param bool                                       $perform
     * @param IApi                                       $api
     * @param Mapper                                     $eventMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     * @param string                                     $fromDate
     * @param string                                     $toDate
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        IApi $api,
        \Schoolbox\External\Event\Mapper $eventMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overridesMapper,
        string $fromDate,
        string $toDate
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->eventMapper = $eventMapper;
        $this->overridesMapper = $overridesMapper;

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

        $staffLookup = (new \Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar\Lookup\Staff($this->api))->generate($this->lBound, $this->uBound);

        /* Generate a list of students
         */
        $this->log->debug('Preloading student compassPersonIds for non-academic classes...');

        $studentLookup = (new \Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar\Lookup\Student($this->api))->generate($this->lBound, $this->uBound);

        /* Gotta use this to figure out who everyone is, given
         * a compass person id (disgusting)
         */
        $attendeesLookup = new Attendees($this->log, $staffLookup, $studentLookup);

        /* We'll need this for later
         */
        $now = \Alaress_Date_Format::now();
        $enrolmentsLookup = new \Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar\Lookup\Enrolments($this->log, $this->api);

        /* Get a list of Schedules to consume
         */
        $academicGroupSchedules = (new \Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar\Lookup\Schedules($this->log, $this->api))->generate();

        $this->log->info('Mining all the Schedules for their Activity Instances...');

        /* Quartic n^4 complexity, but I can't think of a convenient way to improve this
         */
        foreach ($academicGroupSchedules as $academicGroupCode => $schedules) {
            foreach ($schedules['schedules'] as $schedule) {
                foreach ($schedule['activities'] as $activity) {
                    $activityType = (int)$activity['activityType'];

//                    if (!(new Watchlist())->isOnWatchlist($activity['name'], ['Library Lunch Time Duty'])) {
//                        continue;
//                    }

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
                     *
                     * Find them via attendees struct
                     */
                    $enrolments = $attendeesLookup->generate($activity);

                    $this->log->info(sprintf(
                        'Schedule %s: fetched enrolments for activity %s starting on %s: %s students',
                        $schedule['name'],
                        $activity['name'],
                        $activity['start'],
                        \count($enrolments['students'])
                    ));

                    /* Combine sessions and instances
                     */
                    $combinedInstances = (new \Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar\RecombineSessions())->recombine($activity['instances'], $enrolments['sessions']);

                    /* These are instances of classes that occur throughout the year/timetable cycle
                     * We only care about a certain subset of these instances.
                     *
                     * 1. The only activity instances that matter are runningStatus 1. 0 is cancelled
                     * and 1 is draft, neither of which we really would care about
                     * (Cancelled status makes it go away from the compass ui altogether)
                     *
                     * 2. The instance has to be occurring either today, or in the future. Not sure
                     * there is much value importing things that happened yonks ago
                     *
                     * 3. If it's in the future, it should be within the next FUTURE_THRESHOLD months,
                     * otherwise we're going to be importing a boatload of data we might not need.
                     * I recognise that forward planning might be a thing, but still
                     */
                    foreach ($combinedInstances as $combinedInstance) {
                        /* Has to be officially running
                         */
                        if (self::IS_RUNNING !== (int)$combinedInstance['runningStatus']) {
                            $this->log->debug(sprintf(
                                'Activity %s Instance on %s is not running; skipping',
                                $activity['name'],
                                $activity['start']
                            ));

                            continue;
                        }

                        /* Must start today, or in the future
                         */
                        $start = \Alaress_Date_Format::newFromTimestamp(strtotime($combinedInstance['start']));

                        /* Fetch PAST_THRESHOLD days into the past, so people can see where they've been
                         */
                        if ($start->isPast() && $start->diffInDays($now) > self::PAST_THRESHOLD) {
                            $this->log->debug(sprintf(
                                'Activity %s Instance on %s is too far in the past; skipping',
                                $activity['name'],
                                $combinedInstance['start']
                            ));

                            continue;
                        }

                        /* Don't fetch too many from the future
                         */
                        if ($start->isFuture() && $start->diffInDays($now) > self::FUTURE_THRESHOLD) {
                            $this->log->debug(sprintf(
                                'Activity %s Instance on %s is too far into the future; skipping',
                                $activity['name'],
                                $combinedInstance['start']
                            ));

                            continue;
                        }

                        /* Start manufacturing events and relationships for each person
                         * in $enrolments, then save it
                         */
                        $this->log->info(sprintf(
                            'Manufacturing event for Activity %s Instance on %s which originated in schedule %s; using import identifier %s as folder code',
                            $activity['name'],
                            $combinedInstance['start'],
                            $schedule['name'],
                            $activity['name']
                        ));

                        $this->processClass($activity, $combinedInstance, $enrolments, $staffLookup);
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

        /* Save the involvements
         */
        $this->createInvolvements($activity, $activityInstance, $enrolments, $staffLookup, $event);
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
            ->setClassification(\Schoolbox\External\Event\Model::CLASSIFICATION_EVENT)
            ->setGuid($guid)
            ->setSource(Prepare::EVENT_IMPORT_IDENTIFIER);

        /* Save it and remember what we saw in case we need to reuse this later
         */
        $this->log->debug(sprintf(
            'Creating new timetable event %s (%s) on day %s, period %s, %s-%s',
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
     * Save all the involvements for a given $class
     *
     * @param array                           $activity
     * @param array                           $activityInstance
     * @param array                           $enrolments
     * @param array                           $staffLookup
     * @param \Schoolbox\External\Event\Model $event
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
        \Schoolbox\External\Event\Model $event
    ): void {
        /* Name is the same as the Code, in compass
         */
        $activityName = trim((string)$activity['name']);

        /* Alternatively, this might also have additional participating teachers
         * Everyone in it is going
         */
        if (!\count($enrolments['teachers'])) {
            $this->log->debug(sprintf(
                '%s: no additional teachers involved',
                $activityName
            ));
        } else {
            foreach ($enrolments['teachers'] as $externalId) {
                $this->saveStaffInvolvement($event, [
                    'externalId' => $externalId
                ]);
            }
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
                $this->saveStudentInvolvement($event, $externalId);
            }
        }
    }


    /**
     * Save staff involvement as an override, which allows each class
     * to have different teachers depending on their associated sis_event
     *
     * @param Model $event
     * @param array $teacher
     * @return void
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    private function saveStaffInvolvement(
        \Schoolbox\External\Event\Model $event,
        array $teacher
    ): void {
        /* We use the Overrides system to specify a particular person's involvement
         * in a particular sis_event instance, rather than being associated with all
         */
        $override = $this->overridesMapper->createModel()
            ->setEventId($event->getId())
            ->setExternalId($teacher['externalId'])
            ->setAction(\Schoolbox\External\Event\Overrides\Model::ADD)
            ->setSequence(1);

        $this->log->debug(sprintf(
            'Creating new override for %s in timetable event %s - %s, %s, %s-%s',
            $teacher['externalId'],
            $event->getTitle(),
            $event->getDay(),
            $event->getPeriod(),
            $event->getStartAt()->getDatabase(),
            $event->getFinishAt()->getDatabase()
        ));

        if ($this->perform) {
            $this->overridesMapper->save($override);
        }
    }


    /**
     * Save an involvement for user $externalId, whose name is $name,
     * for the sis_event $event
     *
     * @param \Schoolbox\External\Event\Model $event
     * @param string                          $externalId
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function saveStudentInvolvement(
        \Schoolbox\External\Event\Model $event,
        string $externalId
    ): void {
        /* We use the Overrides system to specify a particular person's involvement
         * in a particular sis_event instance, rather than being associated with all
         */
        $override = $this->overridesMapper->createModel()
            ->setEventId($event->getId())
            ->setExternalId($externalId)
            ->setAction(\Schoolbox\External\Event\Overrides\Model::ADD)
            ->setSequence(1);

        $this->log->debug(sprintf(
            'Creating new override for %s in timetable event %s - %s-%s',
            $externalId,
            $event->getTitle(),
            $event->getStartAt()->getDatabase(),
            $event->getFinishAt()->getDatabase()
        ));

        if ($this->perform) {
            $this->overridesMapper->save($override);
        }
    }
}
