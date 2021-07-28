<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\Timetable;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Compass\Base\Endpoint\IApi;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriod;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\Mapper\Exception;

/**
 * Class Definition
 *
 * Timetable definition of days and periods
 *
 * @package Schoolbox\External\Connector\Compass\Base\Endpoint\Timetable
 */
class Definition implements IStrategy
{

    /**
     * Temporarily here to denote the start and end of cycles
     * There appears to be no equivalent concept
     *
     * TODO Decide if you can find these elsewhere, or just use these defaults
     */
    private const CYCLE_START = '2000-01-01 00:00:00';
    private const CYCLE_END = '2999-01-01 00:00:00';


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
     * The API abstraction used for connecting to the external data source.
     */
    private IApi $api;


    /**
     * @var TimetableCycleMapper
     */
    private TimetableCycleMapper $timetableCycleMapper;


    /**
     * @var TimetablePeriodMapper
     */
    private TimetablePeriodMapper $timetablePeriodMapper;


    /**
     * TimetableCycle constructor.
     *
     * @param LoggerInterface       $log
     * @param bool                  $perform
     * @param IApi                  $api
     * @param TimetableCycleMapper  $timetableCycleMapper
     * @param TimetablePeriodMapper $timetablePeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        IApi $api,
        TimetableCycleMapper $timetableCycleMapper,
        TimetablePeriodMapper $timetablePeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->timetableCycleMapper = $timetableCycleMapper;
        $this->timetablePeriodMapper = $timetablePeriodMapper;
    }


    /**
     * Import data from xml into local schema
     */
    public function import(): void
    {
        // I don't know which academic group uses which structure
        $academicGroups = (new AcademicGroups($this->api))->generate();
        $structures     = (new Structures($this->api))->generate();

//        $calendar = $this->api->retrieveDocument('schedule/v1/GetCalendar', ['year' => date('Y')]);
        $schedules = $this->api->retrieveDocument('schedule/v1/GetSchedule', []);


        // Introspect the ImportDayIdentifier and ImportPeriodIdentifier from getSchedule:
        // Use that to figure out the first day and offset
        // Assume schedule[0] is Normal Classes

        $dayPeriodClasses = [];
        foreach ($schedules as $schedule) {
            // Only these classes may be imported
            if (!$schedule['allowImport']) {
                continue;
            }

            // Every schedule contains an activity
            foreach ($schedule['activities'] as $activity) {
                foreach ($activity['instances'] as $instance) {
                    $dayIdentifier    = $instance['importDayIdentifier'] ?? null;
                    $periodIdentifier = $instance['importPeriodIdentifier'] ?? null;
                    $activityName     = $activity['name'] ?? null;
                    if (!isset($dayIdentifier, $periodIdentifier, $activityName)) {
                        continue;
                    }
                    $dayPeriodClasses[$dayIdentifier] ??= [
                        'day'         => $dayIdentifier,
                        'periods'     => [],
                        'firstPeriod' => null,
                        'lastPeriod'  => null,
                    ];
                    $dayPeriodClasses[$dayIdentifier]['periods'][$periodIdentifier] ??= [
                        'period'     => $periodIdentifier,
                        'activities' => [],
                    ];

                    $start  = \Alaress_Date_Format::newFromTimestamp(strtotime($instance['start']))->getDatabase();
                    $finish = \Alaress_Date_Format::newFromTimestamp(strtotime($instance['finish']))->getDatabase();
                    $dayPeriodClasses[$dayIdentifier]['firstPeriod'] = min(
                        $dayPeriodClasses[$dayIdentifier]['firstPeriod'] ?? $start,
                        $start
                    );
                    $dayPeriodClasses[$dayIdentifier]['lastPeriod'] = max(
                        $dayPeriodClasses[$dayIdentifier]['lastPeriod'] ?? $finish,
                        $finish
                    );
                    $dayPeriodClasses[$dayIdentifier]['periods'][$periodIdentifier]['activities'][$activityName][] = [
                        'start'  => $start,
                        'finish' => $finish,
                    ];
                }
            }
        }

        // Sort this jumble
        ksort($dayPeriodClasses);

        foreach ($academicGroups as $academicGroup) {
            foreach ($structures as $structure) {
                // Parse structure into a set of days
                $cycleName       = $academicGroup['name'] . ' ' . $structure['name'];
                $cycleLength     = $structure['dayCount'];
                $dayLength       = $structure['periodCount'];
                $cycleIdentifier = $academicGroup['academicGroupCode'] . ':' . $structure['name'];

                // Can probably determine cycle start/end from the calendar,
                // once I can access it
                $cycleStart = \Alaress_Date_Format::newDatabaseDate(self::CYCLE_START);
                $cycleEnd   = \Alaress_Date_Format::newDatabaseDate(self::CYCLE_END);

                // No idea what dayStart should be
                $dayStart = 1;


                /* Reuse existing cycle with the same identifier, if it exists, and update it
                 */
                try {
                    $timetableCycle = $this->timetableCycleMapper->findOneByColumn('cycle_identifier', $cycleIdentifier);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $timetableCycle = $this->timetableCycleMapper->createModel();
                }

                $timetableCycle
                    ->setCycleIdentifier($cycleIdentifier)
                    ->setTitle($cycleName)
                    ->setCycleLength($cycleLength)
                    ->setDayLength($dayLength)
                    ->setStart($cycleStart)
                    ->setFinish($cycleEnd)
                    ->setStartDay($dayStart);

                $this->log->info(
                    sprintf(
                        'Creating timetable cycle %s (%s) with day length %s',
                        $timetableCycle->getId(),
                        $timetableCycle->getTitle(),
                        $timetableCycle->getDayLength(),
                    )
                );

                if ($this->perform) {
                    $this->timetableCycleMapper->save($timetableCycle);
                }

                /* Save all the periods and associate them with a cycle
                 */
                $dayData = $this->findDays($structure);

                /** @var array $days */
                $days = $dayData['days'];

                /** @var array $periods */
                foreach ($days as $day => $periods) {

                    /** @var TimetablePeriod $period */
                    foreach ($periods as $period) {
                        $period->setDay($day);
                        $period->setCycle($timetableCycle);

                        $this->log->info(sprintf(
                            'Creating period %s (%s-%s) in timetable cycle %s (%s)',
                            $period->getTitle(),
                            $period->getStart()->getDatabaseTime(),
                            $period->getFinish()->getDatabaseTime(),
                            $timetableCycle->getId(),
                            $timetableCycle->getTitle()
                        ));

                        if ($this->perform) {
                            $this->timetablePeriodMapper->save($period);
                        }
                    }
                }
            }
        }
    }


    /**
     * Find all the Days in a given $cycle and the longest Day of them all
     *
     * @param mixed $cycle
     * @return array
     * @throws Exception
     */
    private function findDays($cycle): array
    {
        $struct = [
            /* Each cycle has the potential for different Period structs
             * We only support a rectangular representation (every day must have the same number of periods)
             * Find the day with the most number of periods and assume that to be canonical
             * (some days have no period data)
             */
            'maxPeriods' => $cycle['periodCount'],

            /* Store all the periods here, indexed by Day
              */
            'days' => []
        ];

        /* Explore all the Period data
         */
        $days = $cycle['days'];

        foreach ($days as $day) {
            $periods  = $day['periods'];
            $dayIndex = $day['dayNumber'];

            $struct['days'][$dayIndex] = [];

            foreach ($periods as $period) {
                $periodName = $period['periodName'];
                $sequence   = $period['periodNumber'];
                $start      = $period['start'];
                $finish     = $period['finish'];

                $timetablePeriod = $this->timetablePeriodMapper->createModel()
                    ->setTitle($periodName)
                    ->setDay($dayIndex)
                    ->setSequence($sequence)
                    ->setStart($start)
                    ->setFinish($finish);

                $struct['days'][$dayIndex][] = $timetablePeriod;
            }

            /* Do a quick analysis in case there might be something amiss
             */
            if (!\count($struct['days'][$dayIndex])) {
                $cycleName = $cycle['name'];

                $this->log->warning(
                    sprintf(
                        'Cycle %s, Day %s has no periods.',
                        $cycleName,
                        $dayIndex
                    )
                );
            }
        }

        return $struct;
    }
}
