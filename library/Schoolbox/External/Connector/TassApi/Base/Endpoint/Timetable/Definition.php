<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable;

use Psr\Log\LoggerInterface;
use Schoolbox\CampusMapper;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\DSNCodec;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile\Registry as ProfileRegistry;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\TimetableCycle;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriod;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\Mapper\Exception;

/**
 * Class Definition
 *
 * Timetable definition of days and periods
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable
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
    private $log;


    /**
     * @var Api
     */
    private $api;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private $perform;


    /**
     * @var ProfileRegistry
     */
    private $profileRegistry;


    /**
     * @var TimetableCycleMapper
     */
    private $timetableCycleMapper;


    /**
     * @var TimetablePeriodMapper
     */
    private $timetablePeriodMapper;


    /**
     * TimetableCycle constructor.
     *
     * @param LoggerInterface       $log
     * @param bool                  $perform
     * @param Api[]                 $api
     * @param ProfileRegistry       $profileRegistry
     * @param TimetableCycleMapper  $timetableCycleMapper
     * @param TimetablePeriodMapper $timetablePeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        array $api,
        ProfileRegistry $profileRegistry,
        TimetableCycleMapper $timetableCycleMapper,
        TimetablePeriodMapper $timetablePeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->profileRegistry = $profileRegistry;
        $this->timetableCycleMapper = $timetableCycleMapper;
        $this->timetablePeriodMapper = $timetablePeriodMapper;

       /* Store timetableCache and cycleCache in a registry object that its output
        * can be shared with the next Factory process
        */
    }


    private function preloadAllTimetables()
    {
print_r($this->profileRegistry);
//        /* Find all the student external ids
//         */
//
//        /* Inspect every student's timetable to find the largest quantity of Periods per Day
//         * This will inform schoolbox's classic idea of a Timetable Definition which must be
//         * rectangular; every day will have the maximum number of periods in it, even if the
//         * student's timetable doesn't use it
//         */
//        foreach (externalId) {
//
//            /* Hit That Api to find every student's timetable and cache it somewhere
//             * We'll need to operate on it all again later anyway
//             */
//            data = getStudentTimetable(externalId);
//
//            /* Use the last known maxDays/maxPeriods info as a starting point
//             */
//            maxDays = $cycleCache[data[student_timetable][TT_ID]]['cycleLength'] ?? 0;
//            maxPeriods = $cycleCache[data[student_timetable][TT_ID]]['dayLength'] ?? 0;
//
//            /* Remember the largest Day count (cycle length) for this TT_ID
//             */
//            if (data[student_timetable][DAYMAX] > maxDays)
//               maxDays = data[student_timetable][DAYMAX];
//            }
//
//            /* Remember the largest Period count (day length) for this TT_ID
//             */
//            if (data[student_timetable][PERIOD_MAX] > maxPeriods) {
//                maxPeriods = data[student_timetable][PERIOD_MAX];
//            }
//
//            /* Remember each student's timetable (we'll come back to this later)
//             */
//            $registry->timetableCache[externalId] = data[student_timetable];
//
//            /* To find info about Period Times, you need to inspect each Cycle's STRDAYS element
//             * Each day has its own set of Period Names but we don't support that, so just
//             * get the longest day's Period setup and apply that scheme universally
//             */
//            $longestDay = $cycleCache[data[student_timetable][TT_ID]]['cycleLength'] ?? [];
//
//            foreach (data[student_timetable][STRDAYS] as $day => $periods) {
//                if (\count($periods[PERIODS]) >= maxPeriod) {
//                    $longestDay = $periods[PERIODS];
//                }
//            }
//
//            /* Remember the cycle's info consisting of the last known maxDays and maxPeriods info
//             * as we investigate every data ever
//             */
//            $registry->cycleCache[data[student_timetable][TT_ID]] = [
//                'id' => data[student_timetable][TT_ID],
//                'name' => data[student_timetable][DESC_TEXT],
//                'cycleLength' => $maxDays,
//                'dayLength' => $maxPeriods,
//
//                /* Period scheme that we assume can apply universally
//                 */
//                'periods' => $longestDay,
//
//                /* There doesn't appear to be any info about cycles starting/ending at a particular
//                 * datetime, so just presume the hardcoded defaults for now
//                 */
//                'start' => CYCLE_START,
//                'finish' => CYCLE_END
//            ];
//        }

        /* When we're done, we'll have a list of all the students' timetables
         * and a list of all the TT_ID Timetable Cycles + all its counts
         * and a list of the longest known Day for a given Cycle, so we can
         * provide the periods' names and times
         *
         * From here we can then start to manufacture a timetable definition
         */
    }


    /**
     * Import data from xml into local schema
     */
    public function import(): void
    {
        $this->preloadAllTimetables();
//        /* TODO Collection of timetable cycle equivalents from the endpoint
//         */
//        $cycles = [];
//
//        /* A key to identify this cycle and associate it with an luCampus record
//         */
//        $ext = 'abc';
//
//        /* Find a campus record that could be associated with $ext
//         * You might do that via a lookup or other method than described here which is just an illustration
//         */
//        $campus = CampusMapper::getInstance()->findOneByColumn('ext_timetable_code', $ext);
//
//        foreach ($cycles as $cycle) {
//
//            /* TODO Fill in these cycle vars
//             */
//            $cycleName = '';
//            $cycleLength = '';
//            $dayLength = '';
//            $dayStart = 1;
//
//            $dayData = $this->findDays($cycle);
//
//            /** @var TimetableCycle $timetableCycle */
//            $timetableCycle = $this->timetableCycleMapper->createModel()
//                ->setCycleIdentifier($ext)
//                ->setTitle($cycleName)
//                ->setCycleLength($cycleLength)
//                ->setDayLength($dayLength)
//                ->setStart(\Alaress_Date_Format::newDatabaseDate(self::CYCLE_START))
//                ->setFinish(\Alaress_Date_Format::newDatabaseDate(self::CYCLE_END))
//                ->setStartDay($dayStart);
//
//            $this->log->info(sprintf(
//                'Creating timetable cycle %s (%s) with day length %s',
//                $timetableCycle->getId(),
//                $timetableCycle->getTitle(),
//                $timetableCycle->getDayLength()
//            ));
//
//            if ($this->perform) {
//                $this->timetableCycleMapper->save($timetableCycle);
//            }
//
//            $this->log->info(sprintf(
//                'Associating timetable cycle %s (%s) with campus %s',
//                $timetableCycle->getId(),
//                $timetableCycle->getTitle(),
//                $campus->getName()
//            ));
//
//            /* Save all the periods and associate them with a cycle
//             */
//            /** @var array $days */
//            $days = $dayData['days'];
//
//            /** @var array $periods */
//            foreach ($days as $day => $periods) {
//
//                /** @var TimetablePeriod $period */
//                foreach ($periods as $period) {
//                    $period->setDay($day);
//                    $period->setCycle($timetableCycle);
//
//                    $this->log->info(sprintf(
//                        'Creating period %s (%s-%s) in timetable cycle %s (%s) for campus %s',
//                        $period->getTitle(),
//                        $period->getStart(),
//                        $period->getFinish(),
//                        $timetableCycle->getId(),
//                        $timetableCycle->getTitle(),
//                        $campus->getName()
//                    ));
//
//                    if ($this->perform) {
//                        $this->timetablePeriodMapper->save($period);
//                    }
//                }
//            }
//        }
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
            /* TODO Find out how many periods per day there are (maximum)
             */
            'maxPeriods' => 0,

            /* Store all the periods here, indexed by Day
              */
            'days' => []
        ];

        /* Explore all the Period data
         */
        /* TODO Get a list of days from endpoint data
         */
        $days = [];

        foreach ($days as $day) {

            /* TODO Get a list of periods from endpoint data
             */
            $periods = [];

            /* TODO Decide what day it currently is
             */
            $dayIndex = '';

            /* Figure out which day has the most periods
             * Consider this value and scheme canonical
             */
            $numPeriods = \count($periods);

            if ($numPeriods > $struct['maxPeriods']) {
                $struct['maxPeriods'] = $numPeriods;
            }

            $struct['days'][$dayIndex] = [];

            /* TODO Build a list of periods in a day

            $struct['days'][
                1 => [
                    new TimetablePeriod,
                    new TimetablePeriod,
                    ...
                ],
                2 => [
                    new TimetablePeriod,
                    new TimetablePeriod,
                    ...
                ]
            ]
             */
            /** @var TimetablePeriod $period */
            foreach ($periods as $period) {

                /* TODO Fill in these period vars
                 */
                $periodName = '';
                $sequence = '';
                $start = '';
                $finish = '';

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

                /* TODO Fill in these vars
                 */
                $cycleId = '';
                $cycleName = '';

                $this->log->warning(sprintf(
                    'Cycle %s (%s), Day %s has no periods.',
                    $cycleId,
                    $cycleName,
                    $dayIndex
                ));
            }
        }

        return $struct;
    }
}
