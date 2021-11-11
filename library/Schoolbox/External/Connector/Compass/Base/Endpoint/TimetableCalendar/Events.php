<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar;

use Event\Source\Mapper;
use Psr\Log\LoggerInterface;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventType;
use Schoolbox\Calendar\Relation\CategoryMapper;
use Schoolbox\Calendar\Relation\PersonalMapper;
use Schoolbox\External\Connector\Compass\Base\Endpoint\IApi;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Set up calendar events to represent these class (Activity) instances
 * with all their student relationships and teacher assignations
 *
 * Each Activity instance occurs on a specific datetime
 */
class Events implements IStrategy
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
    private const FUTURE_THRESHOLD = 30 * 1;


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
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * @var bool
     */
    private bool $perform;


    /**
     * @var IApi
     */
    private IApi $api;


    /**
     * @var User
     */
    private User $defaultOwner;


    /**
     * @var EventType
     */
    private EventType $defaultEventType;


    /**
     * @var UserMapper
     */
    private UserMapper $userMapper;


    /**
     * @var EventMapper
     */
    private EventMapper $eventMapper;


    /**
     * @var Mapper
     */
    private \Event\Source\Mapper $eventSourceMapper;


    /**
     * @var PersonalMapper
     */
    private PersonalMapper $relationPersonalMapper;


    /**
     * @var CategoryMapper
     */
    private CategoryMapper $relationFolderMapper;


    /**
     * @var \Schoolbox\Resource\Folder\Mapper
     */
    private \Schoolbox\Resource\Folder\Mapper $folderMapper;


    /**
     * Constructor.
     *
     * @param LoggerInterface                   $log
     * @param bool                              $perform
     * @param IApi                              $api
     * @param User                              $defaultOwner
     * @param EventType                         $defaultEventType
     * @param UserMapper                        $userMapper
     * @param EventMapper                       $eventMapper
     * @param Mapper                            $eventSourceMapper
     * @param PersonalMapper                    $relationPersonalMapper
     * @param CategoryMapper                    $relationFolderMapper
     * @param \Schoolbox\Resource\Folder\Mapper $folderMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        IApi $api,
        User $defaultOwner,
        EventType $defaultEventType,
        UserMapper $userMapper,
        EventMapper $eventMapper,
        \Event\Source\Mapper $eventSourceMapper,
        PersonalMapper $relationPersonalMapper,
        CategoryMapper $relationFolderMapper,
        \Schoolbox\Resource\Folder\Mapper $folderMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;

        $this->defaultOwner = $defaultOwner;
        $this->defaultEventType = $defaultEventType;
        $this->userMapper = $userMapper;
        $this->eventMapper = $eventMapper;
        $this->eventSourceMapper = $eventSourceMapper;
        $this->relationPersonalMapper = $relationPersonalMapper;
        $this->relationFolderMapper = $relationFolderMapper;
        $this->folderMapper = $folderMapper;
    }


    /**
     * Import as calendar events
     */
    public function import(): void
    {
        $this->log->info('Fetching Academic Groups, Enrolments, Schedules and their Activity Instances...');

        /* Some extra details about how many things were made
         */
        $numEvents = 0;
        $numRelationships = 0;

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
        foreach ($academicGroupSchedules as $academicGroupCode => $schedules) {
            foreach ($schedules['schedules'] as $schedule) {
                foreach ($schedule['activities'] as $activity) {
                    $activityType = (int)$activity['activityType'];

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

                    /* We only care about certain activities.
                     *
                     * 1. Activity must have a runningStatus 1. 0 is cancelled and 1 is draft,
                     * neither of which we really would care about
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

                    $this->log->info(sprintf(
                        'Schedule %s: fetched enrolments for activity %s starting on %s: %s students, %s teachers',
                        $schedule['name'],
                        $activity['name'],
                        $activity['start'],
                        \count($enrolments['students']),
                        \count($enrolments['teachers'])
                    ));

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
                    foreach ($activity['instances'] as $activityInstance) {
                        /* Has to be officially running
                         */
                        if (self::IS_RUNNING !== (int)$activityInstance['runningStatus']) {
                            $this->log->debug(sprintf(
                                'Activity %s Instance on %s is not running; skipping',
                                $activity['name'],
                                $activity['start']
                            ));

                            continue;
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
                            'Manufacturing event for Activity %s Instance on %s',
                            $activity['name'],
                            $activityInstance['start']
                        ));

                        /* The relationships here are based on actual schoolbox.user accounts existing
                         * since we have foreigner keys for user.id in the schema. This means UPS must
                         * have run prior to timetable imports...we'll just fumble through it
                         */
                        $numRelationships += $this->createClassEvents($activity, $activityInstance, $enrolments);
                        $numEvents++;
                    }
                }
            }
        }

        /* Make a quick count of the things we made
         */
        $this->log->info(sprintf(
            'Manufactured %s events in total, with %s relationships in total',
            $numEvents,
            $numRelationships
        ));
    }


    /**
     * Create an event that represents an occurrence of this Activity
     *
     * @param array $activity
     * @param array $activityInstance
     * @param array $enrolments
     * @return int
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function createClassEvents(array $activity, array $activityInstance, array $enrolments): int
    {
        /* Arbitrarily concoct a superkey to represent this activity instance
         * Unlocking shared personal events basically makes this useless imho
         */
        $guid = "{$activity['activityId']}.{$activity['importIdentifier']}.{$activityInstance['start']}";

        /* Arbitrarily concoct a random array of event information that we can pass around
         */
        $meta = [
            'location' => trim((string)$activityInstance['location']),
            'title' => trim((string)$activity['name']),
            'startDatetime' => \Alaress_Date_Format::newFromTimestamp(strtotime($activityInstance['start'])),
            'endDatetime' => \Alaress_Date_Format::newFromTimestamp(strtotime($activityInstance['finish'])),
            'guid' => $guid
        ];

        /* Recycle this event for all the people related to it
         *
         * This would have otherwise created N events, one for each student and another for
         * the teacher, which is excessive dupe data
         *
         * For a given class:
         *
         * 25 students in a class = 25 individual personal event records.
         * 100 occurrences of that class in a year = 2500 records spawning per ups run
         * 300 UPS runs in a year (assuming once daily) = 750,000 rows spawning throughout the year via ups
         *
         * Let's say there's 10 individual classes in a school;
         * That's 7,500,000 rows spawning throughout the year for the whole school
         * That's a lot of dupe data
         */
        $event = $this->eventMapper->createModel()->setCampusCollection(new \Schoolbox\Collection());
        $eventSource = $this->eventSourceMapper->createModel();

        /* Start hydrating $event with info from $activity and $activityInstance
         */
        $event
            ->setTitle($meta['title'])
            ->setLocation($meta['location'])
            ->setAuthor($this->defaultOwner)
            ->setOwner($this->defaultOwner)
            ->setStartDateTime($meta['startDatetime'])
            ->setEndDateTime($meta['endDatetime'])
            ->setType($this->defaultEventType)
            ->setAccess(Event::ACCESS_PERSONAL);

        /* Assign event source identifier, assign compass guid (whatever that might be)
         */
        $eventSource
            ->setEvent($event)
            ->setSourceName(Prepare::EVENT_IMPORT_IDENTIFIER)
            ->setGuid($meta['guid']);

        /* Assign Group (homepage/category/folder) relations using $activity['subjectImportIdentifier']
         * as the folder code, assigning to individuals if folder code not found
         */
        try {
            /* TODO Improve this with a hashtable lookup, maybe
             */
            $folder = $this->folderMapper->findByCode($activity['subjectImportIdentifier']);

            /* Update the Access Level to GROUP before saving it
             */
            if ($this->perform) {
                $event->setAccess(Event::ACCESS_GROUP);

                $this->eventMapper->save($event);
                $this->eventSourceMapper->save($eventSource);
            }

            return $this->createGroupEvents($activity, $activityInstance, $event, $folder);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->log->warning(sprintf(
                'Activity %s Instance on %s: cannot find corresponding folder for subjectImportIdentifier %s. Creating personal events instead',
                $activity['name'],
                $activityInstance['start'],
                $activity['subjectImportIdentifier']
            ));

            if ($this->perform) {
                $this->eventMapper->save($event);
                $this->eventSourceMapper->save($eventSource);
            }

            /* Folder wasn't found; install this event for each guy individually
             */
            return $this->createPersonalEvents($activity, $activityInstance, $enrolments, $event);
        }
    }


    /**
     * Create a calendar event with Folder relations
     *
     * @param array                            $activity
     * @param array                            $activityInstance
     * @param Event        $event
     * @param \Schoolbox\Resource\Folder\Model $folder
     * @return int
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function createGroupEvents(
        array $activity,
        array $activityInstance,
        Event $event,
        \Schoolbox\Resource\Folder\Model $folder
    ): int {
        $this->log->debug(sprintf(
            'Activity %s Instance on %s: saving folder relationship with %s',
            $activity['name'],
            $activityInstance['start'],
            $folder->getNameWithCodes()
        ));

        /* Assign people relations from $enrolments - try to find the users if they exist
         * via the usual User lookup technique, skipping if they don't exist
         */
        if ($this->perform) {
            $this->relationFolderMapper->save($this->relationFolderMapper->createModel()
                ->setEventId($event->getId())
                ->setCategoryId($folder->getId()));
        }

        /* Created one relationship with $folder
         */
        return 1;
    }


    /**
     * Create a calendar event with Personal relations
     *
     * @param array                     $activity
     * @param array                     $activityInstance
     * @param array                     $enrolments
     * @param Event $event
     * @return int
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function createPersonalEvents(
        array $activity,
        array $activityInstance,
        array $enrolments,
        Event $event
    ): int {
        $n = 0;

        foreach ($enrolments['students'] as $externalId) {
            try {
                $owner = $this->userMapper->findByExternalId($externalId, Role::STUDENT);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $this->log->warning(sprintf(
                    'Activity %s Instance on %s: cannot find student %s, skipping',
                    $activity['name'],
                    $activityInstance['start'],
                    $externalId
                ));

                continue;
            }

            /* Assign people relations from $enrolments - try to find the users if they exist
             * via the usual User lookup technique, skipping if they don't exist
             */
            if ($this->perform) {
                $this->relationPersonalMapper->save($this->relationPersonalMapper->createModel()
                    ->setEventId($event->getId())
                    ->setUserId($owner->getId()));
            }

            $n++;
        }

        /* Do this for the Lead Teacher as well
         */
        foreach ($enrolments['teachers'] as $externalId) {
            try {
                $owner = $this->userMapper->findByExternalId($externalId, Role::STAFF);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $this->log->warning(sprintf(
                    'Activity %s Instance on %s: cannot find teacher %s, skipping',
                    $activity['name'],
                    $activityInstance['start'],
                    $externalId
                ));

                continue;
            }

            /* Assign people relations from $enrolments - try to find the users if they exist
             * via the usual User lookup technique, skipping if they don't exist
             */
            if ($this->perform) {
                $this->relationPersonalMapper->save($this->relationPersonalMapper->createModel()
                    ->setEventId($event->getId())
                    ->setUserId($owner->getId()));
            }

            $n++;
        }

        /* Created $n personal relationships
         */
        return $n;
    }
}
