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
     * @var Registry
     */
    private $timetableRegistry;


    /**
     * @var Registry
     */
    private $cycleRegistry;


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
     * @param Registry              $timetableRegistry
     * @param Registry              $cycleRegistry
     * @param TimetableCycleMapper  $timetableCycleMapper
     * @param TimetablePeriodMapper $timetablePeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        array $api,
        ProfileRegistry $profileRegistry,
        Registry $timetableRegistry,
        Registry $cycleRegistry,
        TimetableCycleMapper $timetableCycleMapper,
        TimetablePeriodMapper $timetablePeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->profileRegistry = $profileRegistry;

        /* Store timetableCache and cycleCache in a registry object that its output
         * can be shared with the next Factory process
         */
        $this->timetableRegistry = $timetableRegistry;
        $this->cycleRegistry = $cycleRegistry;

        $this->timetableCycleMapper = $timetableCycleMapper;
        $this->timetablePeriodMapper = $timetablePeriodMapper;
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

        /* Find all the student external ids
         */
        $externalIds = $this->profileRegistry->recallKeys();

        /* Inspect every student's timetable to find the largest quantity of Periods per Day
         * This will inform schoolbox's classic idea of a Timetable Definition which must be
         * rectangular; every day will have the maximum number of periods in it, even if the
         * student's timetable doesn't use it
         */
        foreach ($externalIds as $externalId) {

            /* Hit That Api to find every student's timetable and cache it somewhere
             * We'll need to operate on it all again later anyway
             */
            $data = $this->api[DSNCodec::MODULE_LMS_INTEGRATION]->retrieveDocument('getStudentTimetable', [
                'student_code' => $externalId
            ]);

            /* Check that the $externalId has an associated timetable
             * If not, log that this student didn't have a timetable, then skip them
             * There isn't anything we can do with them
             */
            if (!(isset($data['student_timetable']) && \count($data['student_timetable']))) {
                $person = $this->profileRegistry->recall($externalId);

                $this->log->warning(sprintf(
                    'Student %s (%s %s) does not have a timetable.',
                    $externalId,
                    $person['firstname'],
                    $person['lastname']
                ));

                continue;
            }

            /* Otherwise, continue on with the first timetable elmeent
             * (Not sure how many they can actually have in production)
             */
            $timetable = $data['student_timetable'][0];

            /* Remember each student's timetable (we'll come back to this later)
             */
            $this->timetableRegistry->remember((string)$externalId, $timetable);

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

            /* To find info about Period Times, you need to inspect each Cycle's STRDAYS element
             * Each day has its own set of Period Names but we don't support that, so just
             * get the longest day's Period setup and apply that scheme universally
             */
            $longestDay = $this->cycleRegistry->recall($ttid)['cycleLength'] ?? [];

            foreach ($timetable['STRDAYS'] as $day => $periods) {
                if (\count($periods['PERIODS']) >= \count($longestDay)) {
                    $longestDay = $periods['PERIODS'];
                }
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
                'periods' => $longestDay,

                /* There doesn't appear to be any info about cycles starting/ending at a particular
                 * datetime, so just presume the hardcoded defaults for now
                 */
                'start' => self::CYCLE_START,
                'finish' => self::CYCLE_END
            ]);
        }
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

        /* $ext is a key to identify this cycle and associate it with an luCampus record
         * Also happens to be synonymous with $cycle['id'], only because I said so earlier
         */
        foreach ($cycles as $ttid => $cycle) {
            $ext = (string)$ttid;
            $cycleName = $cycle['name'];
            $cycleLength = $cycle['cycleLength'];
            $dayLength = $cycle['dayLength'];
            $cycleStart = \Alaress_Date_Format::newDatabaseDate(self::CYCLE_START);
            $cycleEnd = \Alaress_Date_Format::newDatabaseDate(self::CYCLE_END);
            $dayStart = 1;

            /* Reuse existing cycle with the same identifier, if it exists, and update it
             */
            try {
                $timetableCycle = $this->timetableCycleMapper->findOneByColumn('cycle_identifier', $ext);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $timetableCycle = $timetableCycle = $this->timetableCycleMapper->createModel();
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
                'Creating timetable cycle %s (%s) with day length %s',
                $timetableCycle->getId(),
                $timetableCycle->getTitle(),
                $timetableCycle->getDayLength()
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
        $days = range(1, $cycle['dayLength']);
        $periods = array_values($cycle['periods']);

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
            /** @var TimetablePeriod $period */
            foreach ($periods as $j => $period) {
                $periodName = $period['PRD_DESC'];
                $sequence = $j;
                $start = substr($period['PRD_START'], 11, 8);
                $finish =substr($period['PRD_END'], 11, 8);

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
