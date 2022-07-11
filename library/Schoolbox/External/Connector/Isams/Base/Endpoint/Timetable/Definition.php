<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Timetable;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\FirstMeaningfulValue;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Batchxml;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\TimetableCycle;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriod;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\Mapper\Exception;
use SimpleXMLElement;

/**
 * Class Definition
 *
 * Timetable definition of days and periods
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint\Timetable
 */
class Definition implements IStrategy
{

    /**
     * Temporarily here to denote the start and end of cycles
     * There appears to be no equivalent concept in isams
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
     * @var Batchxml
     */
    private Batchxml $batchxml;


    /**
     * @var TimetableCycleMapper
     */
    private TimetableCycleMapper $timetableCycleMapper;


    /**
     * @var TimetablePeriodMapper
     */
    private TimetablePeriodMapper $timetablePeriodMapper;


    /**
     * @var DayCalibrator
     */
    private DayCalibrator $dayCalibrator;


    /**
     * TimetableCycle constructor.
     *
     * @param LoggerInterface       $log
     * @param bool                  $perform
     * @param Batchxml              $batchxml
     * @param TimetableCycleMapper  $timetableCycleMapper
     * @param TimetablePeriodMapper $timetablePeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Batchxml $batchxml,
        TimetableCycleMapper $timetableCycleMapper,
        TimetablePeriodMapper $timetablePeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->batchxml = $batchxml;
        $this->timetableCycleMapper = $timetableCycleMapper;
        $this->timetablePeriodMapper = $timetablePeriodMapper;
        $this->dayCalibrator = new DayCalibrator($batchxml);
    }


    /**
     * Import data from xml into local schema
     * @throws Exception
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        /* Find a way to identify this Cycle
         */
        $publishedTimetables = $this->batchxml->fetchXml('/iSAMS/TimetableManager/PublishedTimetables/Timetable');

        /* Exit if you can't identify the cycle
         */
        if (!$publishedTimetables) {
            $this->log->debug('Cannot identify cycle identifier for PublishedTimetables in this xml.');
            exit;
        }

        /* MASSIVE CAVEAT
         * Assign the same timetable definition to every campus because there's no obvious
         * correlation between Timetable/Structure/Week and PublishedTimetables/Timetable
         * Also, we haven't seen any evidence that they have multiple timetables or multiple
         * timetable definitions; their xml, however, suggests that it possible in theory
         */
        $cycles = $this->batchxml->fetchXml('/iSAMS/TimetableManager/Structure/Week');

        foreach ($cycles as $cycle) {
            /* Multiple cycles might exist for a given publishedTimetable
             * Each cycle should exist independently of other cycles
             *
             * This means we need to construct an $ext per cycle so that users
             * can assign them to their corresponding luCampus
             *
             * If you don't, you end up getting foreign key issues in the schema
             *
             * For an $ext, you just need to find something that's identifiable to humans
             * Try to find the first meaningful value out of ShortName, Name and Cycle Id
             * (not sure if ShortName is required in isams itself)
             */
            $ext = (new FirstMeaningfulValue())->find([
                trim((string)$cycle->ShortName),
                trim((string)$cycle->Name),
                trim((string)$cycle->xpath('@Id')[0]->Id)
            ]);

            $dayData = $this->findDays($cycle);
            $cycleStart = \Alaress_Date_Format::newDatabaseDate(self::CYCLE_START);
            $cycleEnd = \Alaress_Date_Format::newDatabaseDate(self::CYCLE_END);

            /* Show the cycle identifier in the title
             *
             * This id was something buried in the xml, and you just had to know what it was
             * and configure a campus' external timetable code manually. It'd change on timetable
             * rollovers or whenever a new timetable version was manufactured.
             *
             * This should make it more apparent and easier to access than digging into the
             * Status report where I tried to expose it
             */
            $cycleName = sprintf(
                '%s (%s-%s)',
                trim((string)$cycle->Name),
                $cycleStart->getShortDateOnly(),
                $cycleEnd->getShortDateOnly()
            );

            /* Update the existing Timetable Cycle if it already exists
             * Otherwise delete any old cycles and create a new one
             *
             * This is intended to prevent the accumulation of obsolete cycles
             */
            try {
                $timetableCycle = $this->timetableCycleMapper->findOneByColumn('cycle_identifier', $ext);

                $this->log->info(sprintf(
                    'Reusing an existing cycle, %s (ext %s)',
                    $cycleName,
                    $ext
                ));
            } catch (Exception $e) {
                $timetableCycle = $this->timetableCycleMapper->createModel();

                $this->log->info(sprintf(
                    'Creating a new cycle, %s (ext %s)',
                    $cycleName,
                    $ext
                ));
            }

            $timetableCycle
                ->setTitle($cycleName)
                ->setCycleIdentifier($ext)
                ->setCycleLength(\count($cycle->xpath('Days/Day')))
                ->setDayLength($dayData['maxPeriods'])
                ->setStart($cycleStart)
                ->setFinish($cycleEnd)
                ->setStartDay(1);

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
                        $period->getStart()->getTimeOnly(),
                        $period->getFinish()->getTimeOnly(),
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
     * @param SimpleXMLElement $cycle
     * @return array
     * @throws Exception
     */
    private function findDays(SimpleXMLElement $cycle): array
    {
        $struct = [

            /* Each cycle has the potential for different Period structs
             * We only support a rectangular representation (every day must have the same number of periods)
             * Find the day with the most number of periods and assume that to be canonical
             * (some days have no period data)
             */
            'maxPeriods' => 0,

            /* Store all the periods here, indexed by Day
              */
            'days' => []
        ];

        /* Explore all the Period data
         */
        $days = $cycle->xpath('Days/Day');

        foreach ($days as $day) {
            $periods = $day->xpath('Periods/Period');
            $dayIndex = $this->dayCalibrator->determineSchoolboxDay((int)$day->Day);

            /* Figure out which day has the most periods
             * Consider this value and scheme canonical
             */
            $numPeriods = \count($periods);

            if ($numPeriods > $struct['maxPeriods']) {
                $struct['maxPeriods'] = $numPeriods;
            }

            $struct['days'][$dayIndex] = [];

            foreach ($periods as $period) {
                $timetablePeriod = $this->timetablePeriodMapper->createModel()
                    ->setTitle(trim((string)$period->Name))
                    ->setDay($dayIndex)
                    ->setSequence(trim((string)$period->Ordinal))
                    /* We only care about the Time piece of this date
                     * but must fulfil the interface too
                     */
                    ->setStart(\Alaress_Date_Format::newFromTimestamp(strtotime('1970-01-01 ' . trim((string)$period->StartTime) . ':00')))
                    ->setFinish(\Alaress_Date_Format::newFromTimestamp(strtotime('1970-01-01 ' . trim((string)$period->EndTime) . ':00')));

                $struct['days'][$dayIndex][] = $timetablePeriod;
            }

            /* Do a quick analysis in case there might be something amiss
             */
            if (!\count($struct['days'][$dayIndex])) {
                $this->log->warning(sprintf(
                    'Cycle %s (%s), Day %s has no periods.',
                    trim((string)$cycle->xpath('@Id')[0]),
                    trim((string)$cycle->Name),
                    $dayIndex
                ));
            }
        }

        return $struct;
    }
}
