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
                    $dayIdentifier    = $instance['importDayIdentifier'] ?? null;
                    $periodIdentifier = $instance['importPeriodIdentifier'] ?? null;
                    $activityName     = $activity['name'] ?? null;
                    if (!isset($dayIdentifier, $periodIdentifier, $activityName)) {
                        continue;
                    }
                    $days[$dayIdentifier] ??= [
                        'day'       => $dayIdentifier,
                        'periods'   => [],
                        'firstDate' => null,
                        'lastDate'  => null,
                    ];
                    $days[$dayIdentifier]['periods'][$periodIdentifier] ??= [
                        'period'     => $periodIdentifier,
                        'activities' => [],
                        'firstTime'  => null,
                        'lastTime'   => null,
                    ];

                    // Set first and last occurrence for each day/period
                    $start      = Alaress_Date_Format::newFromTimestamp(strtotime($instance['start']));
                    $finish     = Alaress_Date_Format::newFromTimestamp(strtotime($instance['finish']));
                    $startDate  = $start->getDatabaseDate();
                    $finishDate = $finish->getDatabaseDate();
                    $startTime  = $start->getDatabaseTime();
                    $finishTime = $finish->getDatabaseTime();
                    $days[$dayIdentifier]['firstDate'] = min(
                        $days[$dayIdentifier]['firstDate'] ?? $startDate,
                        $startDate
                    );
                    $days[$dayIdentifier]['lastDate'] = max(
                        $days[$dayIdentifier]['lastDate'] ?? $finishDate,
                        $finishDate
                    );
                    $days[$dayIdentifier]['periods'][$periodIdentifier]['firstTime'] = min(
                        $days[$dayIdentifier]['periods'][$periodIdentifier]['firstTime'] ?? $startTime,
                        $startTime
                    );
                    $days[$dayIdentifier]['periods'][$periodIdentifier]['lastTime'] = max(
                        $days[$dayIdentifier]['periods'][$periodIdentifier]['lastTime'] ?? $finishTime,
                        $finishTime
                    );
                    $days[$dayIdentifier]['periods'][$periodIdentifier]['activities'][$activityName][] = [
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
            'days'       => $days,
            'startDate'  => null,
            'finishDate' => null,
            'startDay'   => null,
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
