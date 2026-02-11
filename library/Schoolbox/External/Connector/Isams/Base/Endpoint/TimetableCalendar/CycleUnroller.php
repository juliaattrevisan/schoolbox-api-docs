<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\TimetableCalendar;

use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;
use SimpleXMLElement;

/**
 * Converts a repeating pattern cyclic grid into a datetime-based schedule
 */
class CycleUnroller
{
    /**
     * We care about stuff that happened this many weeks before today
     */
    public const PAST_THRESHOLD = 1;


    /**
     * We care about stuff that happens this many weeks after today
     */
    public const FUTURE_THRESHOLD = 6;


    /**
     * If it was never set, use 0
     */
    private const DEFAULT_DAY_NUMBER_OFFSET = 0;


    /**
     * Constructor.
     *
     * @param array $api
     */
    public function __construct(private array $api)
    {
    }


    /**
     * Convert a cyclic grid of xml nodes into a schedule equivalent
     *
     * @return array
     */
    public function unroll(): array
    {
        /* The current year. We won't care about other calendar years.
         */
        $year = (int)date('Y');

        $stack = [];

        /* Find all the TimetableAllocation nodes that have an ISOYear
         * that matches the current calendar year
         *
         * Among each of those nodes, we only need to consider the nodes
         * that have an ISOWeek value between PAST_THRESHOLD <= now <= FUTURE_THRESHOLD
         * All other data is too far in the past or too far in the future to care about
         *
         * Intentionally avoid looking beyond the boundaries of the current year for now
         * 1 <= $currentWeek <= 52
         *
         * TODO You could expand upon this so that you can look at the adjacent years
         *  but according to Jez, there's an unlikely need for that...we can adjust
         *  the boundary calculations later
         */
        $currentWeek = (int)\Alaress_Date_Format::now()->getUsingFormat('W');

        /* It turns out that sometimes you can have 53 weeks in a year
         */
        $weeksInYear = (new \DateTime("December 28th, {$year}"))->format('W');

        /* Calculate bounds somewhat crudely
         */
        $pastThreshold = max($currentWeek - self::PAST_THRESHOLD, 1);
        $futureThreshold = min($currentWeek + self::FUTURE_THRESHOLD, $weeksInYear);

        /* Find all TimetableAllocations within these bounds
         */
        $timetableAllocations = $this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/TimetableManager/TimetableAllocations/TimetableAllocation[ISOYear = {$year} and Week >= {$pastThreshold} and Week <= {$futureThreshold}]");

        /* Sometimes iSAMS will incorrectly tell you a school's Day Number which the school
         * can artificially adjust via the suds control panel. Incorporate that offset.
         */
        $options = $this->api[DSN::MODULE_BATCHXML]->getOptions();

        /* Use 0 if it never existed
         */
        $dayNumberOffset = (int)($options['dayNumberOffset'] ?? self::DEFAULT_DAY_NUMBER_OFFSET);

        /* For each of those nodes, find the WeekId that corresponds to that
         * particular week so we can start populating its Days and Periods
         * TimetableAllocation WeekId corresponds to a Structure node <Week Id="...">
         * which contains Days and Periods
         */
        foreach ($timetableAllocations as $timetableAllocation) {
            $weekId = (int)$timetableAllocation->TimetableWeekId;
            $divisionId = (int)$timetableAllocation->Division;

            $week = $this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/TimetableManager/Structure/Week[@Id='{$weekId}']")[0];

            /* The ISO Week number in this calendar year
             */
            $weekIndex = (int)$timetableAllocation->ISOWeek;

            /* Make a megastruct that is basically the "timetable definition"
             * for each week, consisting of its days and periods
             */
            $stack[$divisionId][$weekIndex] = [
                'week' => [
                    'isamsId' => $weekId,
                    'index' => $weekIndex,
                    'name' => (string)$week->Name
                ],
                'days' => $this->findDays($week, $weekIndex, $year, $dayNumberOffset)
            ];
        }

        return $stack;
    }


    /**
     * Find the Days' data corresponding to $week
     *
     * @param SimpleXMLElement $week
     * @param int $weekIndex
     * @param int $year
     * @param int $dayNumberOffset
     * @return array
     */
    private function findDays(SimpleXMLElement $week, int $weekIndex, int $year, int $dayNumberOffset): array
    {
        $dayStruct = [];

        /* For each of the valid WeekId nodes that we found, calculate the datetime
         * that would correspond to each of the Periods in each of the Days contained
         * inside this WeekId node.
         */
        $days = $week->xpath('Days/Day');

        /* Arbitrarily count offsets
         */
        $numDays = count($days);

        for ($dayIndex = 0; $dayIndex < $numDays; $dayIndex++) {
            $day = $days[$dayIndex];
            $dayId = (string)$day->xpath('@Id')[0];

            $date = (new \DateTime())->setISODate($year, $weekIndex, $dayIndex + 1)->format('Y-m-d');
            /* Sometimes iSAMS will incorrectly tell you a school's Day Number which the school
             * can artificially adjust via the suds control panel. Incorporate that offset.
             */
            if (is_numeric((string)$day->Day)) {
                $adjustedDayNumber = (int)$day->Day + $dayNumberOffset;
            } else {
                $adjustedDayNumber = $day->Day;
            }

            /* Assemble into stack
             */
            $dayStruct[$dayIndex] = [
                'isamsId' => $dayId,
                'name' => (string)$day->Name,
                'dayNumber' => (string)$adjustedDayNumber,
                'date' => $date,
                'periods' => $this->findPeriods($day, $date)
            ];
        }

        return $dayStruct;
    }


    /**
     * Find the Periods' data corresponding to $day
     *
     * @param SimpleXMLElement $day
     * @param string           $date
     * @return array
     */
    private function findPeriods(SimpleXMLElement $day, string $date): array
    {
        $periodStruct = [];

        /* For each of the Days and Periods, assign their corresponding startdatetime and
         * finishdatetime values
         */
        foreach ($day->Periods->Period as $period) {
            $periodId = (string)$period->xpath('@Id')[0];

            $startDatetime = sprintf(
                '%s %s:00',
                $date,
                $period->StartTime
            );

            $finishDatetime = sprintf(
                '%s %s:00',
                $date,
                $period->EndTime
            );

            /* Assemble into stack
             */
            $periodStruct[$periodId] = [
                'isamsId' => $periodId,
                'name' => (string)$period->Name,
                'startAt' => $startDatetime,
                'finishAt' => $finishDatetime,
                'classes' => []
            ];
        }

        return $periodStruct;
    }
}
