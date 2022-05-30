<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable;

use DomainException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec;
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
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable
 */
class Definition implements IStrategy
{

    /**
     * Temporarily here to denote the start and end of cycles
     * There appears to be no equivalent concept
     */
    private const CYCLE_START = '2000-01-01 00:00:00';
    private const CYCLE_END = '2999-01-01 00:00:00';
    private const CYCLE_DEFAULT_DAY_NUMBER = 1;


    /**
     * A set of option related to our ability to detect Current Semester
     */
    private const ACADEMIC_PERIOD_KEYS = [
        ['tap1start', 'tap1end'],
        ['tap2start', 'tap2end'],
        ['tap3start', 'tap3end'],
        ['tap4start', 'tap4end']
    ];


    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * @var array
     */
    private array $api;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private bool $perform;


    /**
     * @var Registry
     */
    private Registry $timetableRegistry;


    /**
     * @var Registry
     */
    private Registry $cycleRegistry;


    /**
     * @var TimetableCycleMapper
     */
    private TimetableCycleMapper $timetableCycleMapper;


    /**
     * @var TimetablePeriodMapper
     */
    private TimetablePeriodMapper $timetablePeriodMapper;


    /**
     * @var Lookup
     */
    private Lookup $lookup;


    /**
     * TimetableCycle constructor.
     *
     * @param LoggerInterface       $log
     * @param bool                  $perform
     * @param Api[]                 $api
     * @param Lookup                $lookup
     * @param Registry              $timetableRegistry
     * @param Registry              $cycleRegistry
     * @param TimetableCycleMapper  $timetableCycleMapper
     * @param TimetablePeriodMapper $timetablePeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        array $api,
        Lookup $lookup,
        Registry $timetableRegistry,
        Registry $cycleRegistry,
        TimetableCycleMapper $timetableCycleMapper,
        TimetablePeriodMapper $timetablePeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->lookup = $lookup;

        /* Store timetableCache and cycleCache in a registry object that its output
         * can be shared with the next Factory process
         */
        $this->timetableRegistry = $timetableRegistry;
        $this->cycleRegistry = $cycleRegistry;

        $this->timetableCycleMapper = $timetableCycleMapper;
        $this->timetablePeriodMapper = $timetablePeriodMapper;
    }


    /**
     * Import data from xml into local schema
     */
    public function import(): void
    {

        /* Preload all the students' timetables from everywhere
         *
         * When we're done, we'll have a list of all the students' timetables
         * and a list of all the TT_ID Timetable Cycles, all their counts
         * and a list of the longest known Day for a given Cycle, so we can
         * provide the periods' names and times
         *
         * From here we can then start to manufacture a timetable definition
         */
        $this->preloadAllTimetables();

        /* Collection of timetable cycle equivalents from the endpoint
         */
        $cycles = $this->cycleRegistry->recallAll();

        /* For each cycle we found, identify its start date, day number
         */
        $cycleConfiguration = $this->preloadCycleConfiguration($cycles);

        /* $ext is a key to identify this cycle and associate it with an luCampus record
         * Also happens to be synonymous with $cycle['id'], only because I said so earlier
         */

        foreach ($cycles as $ttid => $cycle) {
            $ext = (string)$ttid;
            $cycleName = $cycle['name'];
            $cycleLength = $cycle['cycleLength'];
            $dayLength = $cycle['dayLength'];
            $cycleStart = $cycleConfiguration[$ttid]['cycleStart'] ?? \Alaress_Date_Format::newDatabaseDate(self::CYCLE_START);
            $cycleEnd = \Alaress_Date_Format::newDatabaseDate(self::CYCLE_END);
            $dayStart = $cycleConfiguration[$ttid]['dayStart'] ?? self::CYCLE_DEFAULT_DAY_NUMBER;

            /* Unique constraint on sis_timetable_period
             * (title, day, cycle_id) tuples must be unique
             * Remember a counter for each dupe we encounter
             */
            $existingTuples = [];

            /* Reuse existing cycle with the same identifier, if it exists, and update it
             */
            try {
                $timetableCycle = $this->timetableCycleMapper->findOneByColumn('cycle_identifier', $ext);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $timetableCycle = $this->timetableCycleMapper->createModel();
            }

            $timetableCycle
                ->setCycleIdentifier($ext)
                ->setTitle($cycleName)
                ->setCycleLength($cycleLength)
                ->setDayLength($dayLength)
                ->setStart($cycleStart)
                ->setFinish($cycleEnd)
                ->setStartDay($dayStart);

            $this->log->info(sprintf(
                'Creating timetable cycle %s (%s) with day length %s, starting on %s with day number %s',
                $timetableCycle->getId(),
                $timetableCycle->getTitle(),
                $timetableCycle->getDayLength(),
                $cycleStart,
                $dayStart
            ));

            if ($this->perform) {
                $this->timetableCycleMapper->save($timetableCycle);
            }

            /* Save all the periods and associate them with a cycle
             */
            $days = $this->findDays($cycle)['days'];

            /** @var array $periods */
            foreach ($days as $day => $periods) {

                /** @var TimetablePeriod $period */
                foreach ($periods as $period) {
                    $period->setDay($day);
                    $period->setCycle($timetableCycle);

                    /* We have a unique constraint on (title, day, cycle) in sis_timetable_period
                     * Sometimes schools have multiple instances of the same name on a given day
                     *
                     * e.g. two Tutor periods without any distinction in their name occur in the
                     * same day in a given cycle (one is a morning session, another in the afternoon)
                     *
                     * Look ahead to see if it exists; if it does, augment the title - but schools
                     * should have unique period names per day instead.
                     */
                    try {
                        $existing = $this->timetablePeriodMapper->findOneByColumnList([
                            'title' => $period->getTitle(),
                            'day' => $period->getDay(),
                            'cycle_id' => $timetableCycle->getId()
                        ]);

                        /* Remember the count, augment the name
                         */
                        if (!isset($existingTuples[$period->getDay()][$period->getTitle()])) {
                            $existingTuples[$period->getDay()][$period->getTitle()] = 0;
                        }

                        $existingTuples[$period->getDay()][$period->getTitle()]++;

                        $newTitle = "{$period->getTitle()} ({$existingTuples[$period->getDay()][$period->getTitle()]})";

                        $this->log->warning(sprintf(
                            'Augmented %s since it occurs multiple times in day %s and cycle %s',
                            $period->getTitle(),
                            $period->getDay(),
                            $period->getCycle()->getId()
                        ));

                        $period->setTitle($newTitle);
                    } catch (\Schoolbox\Mapper\Exception $e) {
                        // noop; doesn't already exist, just create it as normal
                    }

                    $this->log->info(sprintf(
                        'Creating period %s (%s-%s) in timetable cycle %s (%s)',
                        $period->getTitle(),
                        $period->getStart(),
                        $period->getFinish(),
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


    /**
     * For each $ttid, find the most recent VALUE <= today's date
     * When you find it, remember its VALUE and DAY_CODE for later
     *
     * @param array $cycles
     * @return array
     * @throws DomainException
     * @throws RuntimeException
     */
    private function preloadCycleConfiguration(array $cycles): array
    {
        $today   = date('Y-m-d');
        $payload = [];

        foreach ($cycles as $ttid => $cycle) {
            $timetableCalendar = $this->api[Codec\DSN::MODULE_LMS_INTEGRATION]->retrieveDocument('getTimetableCalendar', [
                'tt_id' => $ttid
            ]);

            /* If tass hasn't got any data for $ttid, exit early
             */
            if (!$timetableCalendar['tt_calendar'] || !\count($timetableCalendar['tt_calendar'])) {
                $this->log->warning(sprintf(
                    'Skipping: no getTimetableCalendar data for ttid %s when preloading cycle configuration',
                    $ttid
                ));

                continue;
            }

            /*
             * Index the $timetableCalendar data by (ttid, value)
             * ensuring we only remember meaningful records:
             *   the date (and record) closest to today, but not in the future, or
             *   the date (and record) closest to today in the future ,to avoid calculating day number from self::CYCLE_START
             */
            $closestPast   = ['VALUE' => ''];
            $closestFuture = ['VALUE' => ''];
            foreach ($timetableCalendar['tt_calendar'] as $current) {
                // if DAY_CODE is not set (i.e. 0, '', null), move on
                if (empty($current['DAY_CODE'])) {
                    continue;
                }
                if (
                    ('' === $closestFuture['VALUE'])
                    || ($today < $current['VALUE'] && $current['VALUE'] < $closestFuture['VALUE'])
                ) {
                    $closestFuture = $current;
                }
                if ($closestPast['VALUE'] < $current['VALUE'] && $current['VALUE'] <= $today) {
                    $closestPast = $current;
                }
            }

            if ('' !== $closestFuture['VALUE'] || '' !== $closestPast['VALUE']) {
                $record = '' !== $closestPast['VALUE'] ? $closestPast : $closestFuture;
                /* Mine it for all its worth
                 */
                $payload[$ttid] = [

                    /* Cycle start date
                     */
                    'cycleStart' => $record['VALUE'],

                    /* Starting Day number
                     */
                    'dayStart'   => $record['DAY_CODE'],
                ];
            }
        }

        /* Return (ttid, startDate, dayNumber) tuples for later
         */
        return $payload;
    }


    /**
     * Get all the students' timetables, which we use to divine a schoolbox Timetable Definition
     * In tass, students can have seemingly unique timetables with unique definitions (there isn't
     * really an equivalent of Timetable Definition in tass) so we have to infer something similar
     * as best we can
     *
     * @throws DomainException
     * @throws RuntimeException
     */
    private function preloadAllTimetables(): void
    {

        /* Students
         * Find all the external ids
         */
        $students = $this->lookup->getStudents();

        /* Staff
         * Find all the external ids
         */
        $staff = $this->lookup->getStaff();

        /* Remind people this might take a while
         */
        $this->log->info(sprintf(
            'Preloading timetables for %s students and %s staff - this might take a while',
            \count($students),
            \count($staff)
        ));

        /* Inspect every student's timetable to find the largest quantity of Periods per Day
         * This will inform schoolbox's classic idea of a Timetable Definition which must be
         * rectangular; every day will have the maximum number of periods in it, even if the
         * student's timetable doesn't use it
         */
        foreach ($students as $externalId => $person) {
            /* Hit That Api to find every student's timetable and cache it somewhere
             * We'll need to operate on it all again later anyway
             */
            $data = $this->api[Codec\DSN::MODULE_LMS_INTEGRATION]->retrieveDocument('getStudentTimetable', [
                'student_code' => $externalId
            ]);

            $this->preloadTimetable((string)$externalId, $person, $data, 'student_timetable');
        }

        /* Make a naive guess as to what year and semester we are in
         * The getTeacherTimetable endpoint is different to students' version
         */
        $year = date('Y');
        $semester = (string)$this->getCurrentSemester();

        $options = $this->api[Codec\DSN::MODULE_LMS_INTEGRATION]->getOptions();

        /* Inspect every staff's timetable
         */
        foreach ($staff as $externalId => $person) {
            $data = $this->api[Codec\DSN::MODULE_LMS_INTEGRATION]->retrieveDocument('getTeacherTimetable', [
                'teacher_code' => $externalId,
                'year_num' => $year,
                'semester' => $semester
            ]);

            $this->preloadTimetable((string)$externalId, $person, $data, 'teacher_timetable');
        }
    }


    /**
     * This bypasses the dependency on tass to fix the way to get Current Semester
     *
     * For the given set of TASS Academic Period dates, find out which "semester" we are currently in
     * A school running 4 term years will have $semester available in tass as 1 through to 4, which
     * isn't what a semester really is, because tass reasons...
     *
     * Check each of the pairs until we find the first match where: Start <= Now <= End
     *
     * @param array $options
     * @return int
     */
    private function detectSemester(array $options): int
    {
        $now = \Alaress_Date_Format::now();

        foreach (self::ACADEMIC_PERIOD_KEYS as $index => $pair) {
            /* If both were defined, check Start <= Now <= End
             * This returns the first semester that fulfils the date requirements
             *
             * Otherwise, continue trying
             */
            [$startKey, $endKey] = $pair;

            $start = trim((string)$options[$startKey]);
            $end = trim((string)$options[$endKey]);

            /* One of the pairs is inconclusive - keep going
             */
            if ('' === $start || '' === $end) {
                continue;
            }

            /* Check Alaress Date Format boundaries
             */
            $startDate = \Alaress_Date_Format::newDatabaseDate("{$start} 00:00:00");
            $endDate = \Alaress_Date_Format::newDatabaseDate("{$end} 23:59:59");

            /* If it is, return that $pair's index+1, since:
             *     (tap1start, tap1end) is index 0 in ACADEMIC_PERIOD_KEYS
             *     but it corresponds to "semester 1" in tass.
             */
            if ($now->between($startDate, $endDate)) {
                return $index + 1;
            }
        }

        /* If none of the Academic Periods help us figure out what semester
         * we're currently in, fall back to the default presumptive behaviour of
         * guessing that Jan-Jun is Semester 1, and everything else is semester 2
         */
        return $this->getCurrentSemester();
    }


    /**
     * Returns 1 if we are currently in first semester, defined by being in January-June inclusive
     * Otherwise semester 2
     *
     * @return int
     */
    private function getCurrentSemester(): int
    {
        return (int)date('n') <= 6 ? 1 : 2;
    }


    /**
     * Fetch the timetable data for user $externalId
     *
     * This algorithm is the same for students and staff and the data structures are
     * mostly synonymous, except for a $key name being different and the $data is
     * fetched with some different params
     *
     * @param string $externalId
     * @param array  $person
     * @param array  $data
     * @param string $key
     */
    private function preloadTimetable(string $externalId, array $person, array $data, string $key): void
    {

        /* Check that the given $externalId had any timetable data at all
         */
        if (!(isset($data[$key]) && \count($data[$key]))) {
            $this->log->warning(sprintf(
                'Timetable not found for %s (%s).',
                trim("{$person['firstname']} {$person['lastname']}"),
                $externalId
            ));

            return;
        }

        /* Let us know so we can test with their id
         */
        $this->log->info(sprintf(
            'Processing timetable definition for %s (%s).',
            trim("{$person['firstname']} {$person['lastname']}"),
            $externalId
        ));

        /* Otherwise, continue on with the first timetable element
         * (Not sure how many they can actually have in production)
         */
        $timetable = $data[$key][0];

        /* Remember each student's timetable (we'll come back to this later)
         */
        $this->timetableRegistry->remember($externalId, $timetable);

        /* Use the last known maxDays/maxPeriods info as a starting point
         */
        $ttid = (string)$timetable['TT_ID'];

        $maxDays = $this->cycleRegistry->recall($ttid)['cycleLength'] ?? 0;
        $maxPeriods = $this->cycleRegistry->recall($ttid)['dayLength'] ?? 0;

        /* Remember the largest Day count (cycle length) for this TT_ID
         */
        if ($timetable['DAYMAX'] > $maxDays) {
            $maxDays = $timetable['DAYMAX'];
        }

        /* Remember the largest Period count (day length) for this TT_ID
         */
        if ($timetable['PERIODMAX'] > $maxPeriods) {
            $maxPeriods = $timetable['PERIODMAX'];
        }

        /* Remember the cycle's info consisting of the last known maxDays and maxPeriods info
         * as we investigate every data ever
         */
        $this->cycleRegistry->remember($ttid, [
            'id' => $ttid,
            'name' => $timetable['DESC_TEXT'],
            'cycleLength' => $maxDays,
            'dayLength' => $maxPeriods,

            /* Period scheme that we assume can apply universally
             */
            'periods' => $timetable['STRDAYS'],

            /* There doesn't appear to be any info about cycles starting/ending at a particular
             * datetime, so just presume the hardcoded defaults for now
             */
            'start' => self::CYCLE_START,
            'finish' => self::CYCLE_END
        ]);

        $this->log->debug(sprintf(
            'Remembering %s (%s) had cycleLength %s days, dayLength %s periods',
            $ttid,
            $timetable['DESC_TEXT'],
            $maxDays,
            $maxPeriods
        ));
    }


    /**
     * Find all the Days in a given $cycle and the longest Day of them all
     *
     * @param array $cycle
     * @return array
     * @throws Exception
     */
    private function findDays(array $cycle): array
    {
        $struct = [

            /* Each cycle has the potential for different Period structs
             * We only support a rectangular representation (every day must have the same number of periods)
             * Find the day with the most number of periods and assume that to be canonical
             * (some days have no period data)
             */
            'maxPeriods' => $cycle['dayLength'],

            /* Store all the periods here, indexed by Day
              */
            'days' => []
        ];

        /* Explore all the Period data
         */
        $days = range(1, $cycle['cycleLength']);

        foreach ($days as $i => $dayIndex) {
            /* Build a list of periods in a day

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

            $periods = $cycle['periods'][$dayIndex]['PERIODS'];

            foreach ($periods as $j => $period) {
                $periodName = $period['PRD_DESC'];
                $sequence = $j;

                /* Tass doesn't use the date part,
                 * Tass includes a microseconds part (sigh)
                 * So we have to ditch these bits and swipe what we need
                 */
                $start = substr($period['PRD_START'], 11, 8);
                $finish = substr($period['PRD_END'], 11, 8);

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
                $cycleId = $cycle['id'];
                $cycleName = $cycle['name'];

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
