<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Example\Base\Endpoint\Timetable;

use Psr\Log\LoggerInterface;
use Schoolbox\CampusMapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\TimetableCampus;
use Schoolbox\External\TimetableCampusMapper;
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
 * @package Schoolbox\External\Connector\Example\Base\Endpoint\Timetable
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
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private $perform;


    /**
     * @var CampusMapper
     */
    private $campusMapper;


    /**
     * @var TimetableCampusMapper
     */
    private $timetableCampusMapper;


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
     * @param CampusMapper          $campusMapper
     * @param TimetableCampusMapper $timetableCampusMapper
     * @param TimetableCycleMapper  $timetableCycleMapper
     * @param TimetablePeriodMapper $timetablePeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        CampusMapper $campusMapper,
        TimetableCampusMapper $timetableCampusMapper,
        TimetableCycleMapper $timetableCycleMapper,
        TimetablePeriodMapper $timetablePeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->campusMapper = $campusMapper;
        $this->timetableCampusMapper = $timetableCampusMapper;
        $this->timetableCycleMapper = $timetableCycleMapper;
        $this->timetablePeriodMapper = $timetablePeriodMapper;
    }


    /**
     * Import data from xml into local schema
     */
    public function import(): void
    {
        /* TODO Collection of timetable cycle equivalents from the endpoint
         */
        $cycles = [];

        /* TODO Normally each campus has its own cycle; amend accordingly
         */
        $campuses = $this->campusMapper->findAll();

        foreach ($campuses as $campus) {
            foreach ($cycles as $cycle) {

                /* TODO Fill in these cycle vars
                 */
                $cycleName = '';
                $cycleLength = '';
                $dayLength = '';
                $dayStart = 1;

                $dayData = $this->findDays($cycle);

                /** @var TimetableCycle $timetableCycle */
                $timetableCycle = $this->timetableCycleMapper->createModel()
                    ->setId($campus->getId())
                    ->setTitle($cycleName)
                    ->setCycleLength($cycleLength)
                    ->setDayLength($dayLength)

                    /* What is this?
                     */
                    ->setRegular(1);

                /** @var TimetableCampus $timetableCampus */
                $timetableCampus = $this->timetableCampusMapper->createModel()
                    ->setCycle($timetableCycle)
                    ->setCampus($campus)
                    ->setStart(\Alaress_Date_Format::newDatabaseDate(self::CYCLE_START))
                    ->setFinish(\Alaress_Date_Format::newDatabaseDate(self::CYCLE_END))
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

                $this->log->info(sprintf(
                    'Associating timetable cycle %s (%s) with campus %s',
                    $timetableCycle->getId(),
                    $timetableCycle->getTitle(),
                    $campus->getName()
                ));

                if ($this->perform) {
                    $this->timetableCampusMapper->save($timetableCampus);
                }

                /* Save all the periods and associate them with a cycle
                 */
                /** @var array $days */
                $days = $dayData['days'];

                foreach ($days as $day => $periods) {

                    /** @var array $periods */
                    /** @var TimetablePeriod $period */
                    foreach ($periods as $period) {
                        $period->setDay($day);
                        $period->setCycle($timetableCycle);

                        $this->log->info(sprintf(
                            'Creating period %s (%s-%s) in timetable cycle %s (%s) for campus %s',
                            $period->getTitle(),
                            $period->getStart(),
                            $period->getFinish(),
                            $timetableCycle->getId(),
                            $timetableCycle->getTitle(),
                            $campus->getName()
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
