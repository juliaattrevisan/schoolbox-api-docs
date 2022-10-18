<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\TimetableCalendar;

/**
 * Convert isams cyclic grid into schedule format and shank in
 * all the humans involved over the given time interval
 */
class Classes
{
    /**
     * Constructor.
     */
    public function __construct()
    {
    }


    /**
     * @return void
     * @throws \DebugBar\DebugBarException
     */
    public function import(): void
    {
        /* Unroll the cyclic grid into a flat calendar format
         * (this part is pretty quick)
         */
        $timetableDefinition = (new CycleUnroller())->unroll($nodes);
        $scheduleCompiler = new ScheduleCompiler($nodes, $timetableDefinition);

        /* Shank information about the periods occurring during $timetableDefinition
         */
        $schedules = $scheduleCompiler->findSchedules();

        /* Proceed through each week, day and period;
         * obtain more detailed info as you iteratively proceed
         * (this part so slow we do it separately)
         */
        foreach ($timetableDefinition as $weekId => $weekData) {
            foreach ($weekData['days'] as $day) {
                foreach ($day['periods'] as $periodId => $periodData) {
                    $classes = $schedules[$periodId];

                    /* This is all the subjects occurring on this particular period
                     * in the timetable
                     */
                    $subjects = [];

                    foreach ($classes['schedule'] as $scheduleId => $classData) {
                        $subjects[] = $scheduleCompiler->findSubject($classData['code']);
                    }

                    /* Process detailed data procedurally, saving on a mega-preload
                     * of the timetable struct in its most detailed format
                     *
                     * * create classEntry
                     * * create regular staff involvement
                     * * create overrides for subby teachers
                     * * create regular student involvements
                     * * shank it into suds town
                     */
                    new \Alaress_Probe($classes, $subjects);
                }
            }
        }
    }
}
