<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\External\Connector\Compass\Base\Endpoint\IApi;
use Schoolbox\External\Importer\Endpoint\IStrategy;

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
     * Number of months into the future whose Activity Instances we import,
     * used to prevent importing billions of events from a distant future
     * that might not really have much relevance to today
     *
     * Defaults to 6 months but we can tune that later.
     */
    private const FUTURE_THRESHOLD = 6;


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
     * Constructor.
     *
     * @param LoggerInterface $log
     * @param bool            $perform
     * @param IApi            $api
     * @param EventMapper     $eventMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        IApi $api,
        EventMapper $eventMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;

        $this->eventMapper = $eventMapper;
    }


    /**
     * @throws \DebugBar\DebugBarException
     */
    public function import(): void
    {
        /* We'll need this for later
         */
        $now = \Alaress_Date_Format::now();

        /* Get a list of Schedules to consume
         */
        $academicGroupSchedules = (new Lookup\Schedules($this->api))->generate();

        /* Quartic n^4 complexity, but I can't think of a convenient way to improve this
         */
        foreach ($academicGroupSchedules as $academicGroupCode => $schedules) {
            foreach ($schedules['schedules'] as $schedule) {
                foreach ($schedule['activities'] as $activity) {
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
                    $enrolments = (new Lookup\Enrolments($this->api))->generate($activity['name'], (string)$academicGroupCode);

                    $this->log->debug(sprintf(
                        'Fetched enrolments for activity %s starting on %s: %s students, %s teachers',
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

                        /* TODO maybe fetch a week into the past, so people can see where they've been
                         */
                        if ($start->isPast()) {
                            $this->log->debug(sprintf(
                                'Activity %s Instance on %s is in the past; skipping',
                                $activity['name'],
                                $activityInstance['start']
                            ));

                            continue;
                        }

                        /* Don't fetch too many from the future
                         */
                        if ($start->diffInMonths($now) > self::FUTURE_THRESHOLD) {
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

                    }
                }
            }
        }
    }
}