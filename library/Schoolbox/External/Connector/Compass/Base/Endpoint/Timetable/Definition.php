<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\Timetable;

use Alaress_Date_Format;
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
        $schedule       = (new Schedule($this->api))->generate();

        foreach ($academicGroups as $academicGroup) {
            // Parse structure into a set of days
            $cycleName       = $academicGroup['name'];
            $cycleLength     = $schedule['dayCount'];
            $dayLength       = $schedule['periodCount'];
            $cycleIdentifier = $academicGroup['academicGroupCode'];

            $cycleStart = new Alaress_Date_Format($schedule['startDate'], Alaress_Date_Format::DATABASE_DATE_FORMAT);
            $cycleEnd   = new Alaress_Date_Format($schedule['finishDate'], Alaress_Date_Format::DATABASE_DATE_FORMAT);
            $dayStart   = $schedule['startDay'];

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
            $dayData = $this->findDays($schedule);

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
            $dayIndex = $day['day'];

            $struct['days'][$dayIndex] = [];

            foreach ($periods as $i => $period) {
                $periodName = $period['name'];
                $sequence   = $i;
                $start      = new Alaress_Date_Format($period['startTime'], Alaress_Date_Format::DATABASE_TIME_FORMAT);
                $finish     = new Alaress_Date_Format($period['finishTime'], Alaress_Date_Format::DATABASE_TIME_FORMAT);

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
                $this->log->warning(
                    sprintf(
                        'Day %s has no periods.',
                        $dayIndex
                    )
                );
            }
        }

        return $struct;
    }
}
