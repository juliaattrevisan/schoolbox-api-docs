<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\Timetable;

use Alaress_Date_Format;
use Schoolbox\External\Connector\Compass\Base\Endpoint\IApi;

/**
 * Data generator for the schedule from Compass.
 *
 * A timetable structure (days, periods, start date, end date) can be inferred
 * from the schedule.
 */
class Schedule
{
    /**
     * API for communicating with Compass.
     */
    private IApi $api;

    /**
     * Constructor
     *
     * @param IApi $api
     */
    public function __construct(IApi $api)
    {
        $this->api = $api;
    }

    /**
     * Generate and return schedule.
     *
     * @return array
     */
    public function generate(): array
    {
        // Prebuild periods from structure
        $structures   = $this->api->retrieveDocument('schedule/v1/GetStructures', []);
        $periodLookup = [];
        foreach ($structures['d'] as $structure) {
            foreach ($structure['days'] as $i => $day) {
                // Assume that day number is numeric, and the days are in order
                $dayNumber = $i + 1;
                foreach ($day['periods'] as $period) {
                    $periodId                            = $period['periodId'];
                    $periodLookup[$dayNumber][$periodId] = [
                        'code'       => $periodId,
                        'name'       => $period['periodName'],
                        'startTime'  => Alaress_Date_Format::newFromTimestamp(strtotime($period['startTime']))->getDatabaseTime(),
                        'finishTime' => Alaress_Date_Format::newFromTimestamp(strtotime($period['finishTime']))->getDatabaseTime(),
                        'activities' => [],
                    ];
                }
            }

            // Only care about the first (default) schedule
            break;
        }


        // Sort periods by start time, then by finish
        foreach ($periodLookup as $dayNumber => $periods) {
            uasort(
                $periods,
                function ($a, $b) {
                    $startDiff = $a['startTime'] <=> $b['startTime'];
                    if ($startDiff) {
                        return $startDiff;
                    }
                    $finishDiff = $a['finishTime'] <=> $b['finishTime'];
                    if ($finishDiff) {
                        return $finishDiff;
                    }
                    return 0;
                }
            );
            $periodLookup[$dayNumber] = $periods;
        }


        $schedules = $this->api->retrieveDocument('schedule/v1/GetSchedule', []);
        $days      = [];
        foreach ($schedules as $schedule) {
            // Only schedules which have themselves been imported may be
            // imported into Schoolbox?
            if (!$schedule['allowImport']) {
                continue;
            }

            // Every schedule contains an activity
            foreach ($schedule['activities'] as $activity) {
                foreach ($activity['instances'] as $instance) {
                    // Day import identifier always seems to be a number?
                    // We can only make a giant assumption that that day number
                    // matches the day number we derived before.
                    $dayIdentifier    = $instance['importDayIdentifier'] ?? null;
                    $periodIdentifier = $instance['importPeriodIdentifier'] ?? null;
                    $activityCode     = $activity['subjectImportIdentifier'] ?? null;
                    $periodData       = $periodLookup[$dayIdentifier] ?? null;
                    if (!isset($dayIdentifier, $periodIdentifier, $activityCode, $periodData)) {
                        continue;
                    }
                    $days[$dayIdentifier] ??= [
                        'day'       => $dayIdentifier,
                        'periods'   => $periodLookup[$dayIdentifier],
                        'firstDate' => null,
                        'lastDate'  => null,
                    ];

                    // Set first and last date that this class occurs on.
                    // That will be used to derive the cycle's start/finish date
                    // and start day.
                    $start      = Alaress_Date_Format::newFromTimestamp(strtotime($instance['start']));
                    $finish     = Alaress_Date_Format::newFromTimestamp(strtotime($instance['finish']));
                    $startDate  = $start->getDatabaseDate();
                    $finishDate = $finish->getDatabaseDate();
                    $days[$dayIdentifier]['firstDate'] = min(
                        $days[$dayIdentifier]['firstDate'] ?? $startDate,
                        $startDate
                    );
                    $days[$dayIdentifier]['lastDate'] = max(
                        $days[$dayIdentifier]['lastDate'] ?? $finishDate,
                        $finishDate
                    );
                    $days[$dayIdentifier]['periods'][$periodIdentifier]['activities'][$activityCode][] = [
                        'name'   => $activity['name'] ?? null,
                        'code'   => $activityCode,
                        'start'  => $start->getDatabase(),
                        'finish' => $finish->getDatabase(),
                    ];
                }
            }
        }

        // Sort the list of days: these are assumed to be numbers?
        ksort($days);

        // Figure out start date, finish date, first day from the day list
        $result = [
            'days'        => $days,
            'dayCount'    => count($periodLookup),
            'periodCount' => max(array_map('count', $periodLookup)),
            'startDate'   => null,
            'finishDate'  => null,
            'startDay'    => null,
        ];
        foreach ($days as $day) {
            $result['startDate']  = min($result['startDate'] ?? $day['firstDate'], $day['firstDate']);
            $result['finishDate'] = max($result['finishDate'] ?? $day['lastDate'], $day['lastDate']);
            if ($result['startDate'] === $day['firstDate']) {
                $result['startDay'] = $day['day'];
            }
        }
        return $result;
    }
}
