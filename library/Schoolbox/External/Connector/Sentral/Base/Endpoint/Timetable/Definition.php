<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\Timetable;

use Psr\Log\LoggerInterface;
use Schoolbox\CampusMapper;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Registry;
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
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint\Timetable
 */
class Definition implements IStrategy
{

    /**
     * No obvious names found; I just made one up
     */
    public const CYCLE_IDENTIFIER = 'Sentral Timetable';


    /**
     * Temporarily here to denote the start and end of cycles
     * There appears to be no equivalent concept
     *
     * I don't think these come out int he API anywhere even if they are vaguely mentioned here:
     * https://int-dev.sentral.school/s-3kYwLp/timetables/setup/start_weeks
     */
    private const CYCLE_START = '2000-01-01 00:00:00';
    private const CYCLE_END = '2999-01-01 00:00:00';


    /**
     * Currently hardcoded to obtain mon-fri of the current week
     */
    private const CYCLE_LENGTH = 5;


    /**
     * Sentral seem to have an 11-period day hardcoded in their system
     * https://int-dev.sentral.school/s-3kYwLp/timetables/setup/period_times
     *
     * I can't find a place where you can edit those
     */
    private const DAY_LENGTH = 11;


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
     * @var Registry
     */
    private $registry;


    /**
     * @var CampusMapper
     */
    private $campusMapper;


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
     * @param Registry              $registry
     * @param CampusMapper          $campusMapper
     * @param TimetableCycleMapper  $timetableCycleMapper
     * @param TimetablePeriodMapper $timetablePeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Registry $registry,
        CampusMapper $campusMapper,
        TimetableCycleMapper $timetableCycleMapper,
        TimetablePeriodMapper $timetablePeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->registry = $registry;
        $this->campusMapper = $campusMapper;
        $this->timetableCycleMapper = $timetableCycleMapper;
        $this->timetablePeriodMapper = $timetablePeriodMapper;
    }


    /**
     * Import data from xml into local schema
     */
    public function import(): void
    {
        $periodsInDays = $this->findPeriodsInDays();

        /* $ext is a key to identify this cycle and associate it with an luCampus record
         */
        $ext = self::CYCLE_IDENTIFIER;

        /* I've only seen one cycle so far
         */
        $cycleName = self::CYCLE_IDENTIFIER;
        $cycleLength = self::CYCLE_LENGTH;
        $dayLength = \count($periodsInDays);
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
        $dayData = $this->findDays($periodsInDays);

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


    /**
     * Find all the Days in a given $cycle and the longest Day of them all
     *
     * @param array $periodsInDays
     * @return array
     * @throws Exception
     */
    private function findDays(array $periodsInDays): array
    {
        $struct = [

            /* Each cycle has the potential for different Period structs
             * We only support a rectangular representation (every day must have the same number of periods)
             * Find the day with the most number of periods and assume that to be canonical
             * (some days have no period data)
             */
            'maxPeriods' => \count($periodsInDays),

            /* Store all the periods here, indexed by Day
              */
            'days' => []
        ];

        /* Explore all the Period data
         */
        $periods = array_values($periodsInDays);

        for ($i = 0; $i < self::CYCLE_LENGTH; $i++) {
            $dayIndex = $i+1;

            $struct['days'][$dayIndex] = [];

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
            foreach ($periods as $period) {
                $periodName = $period['name'];
                $sequence = $period['sequence'];
                $start = $period['start'];
                $finish = $period['finish'];

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
                $cycleId = self::CYCLE_IDENTIFIER;
                $cycleName = self::CYCLE_IDENTIFIER;

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


    /**
     * Rearrange the data structure into something sensible
     */
    private function findPeriodsInDays(): array {
        $periodsInDays = [];

        foreach ($this->registry->recallAll() as $studentId => $data) {

            /* No timetable found for this person
             * Skip them
             */
            if (null === $data['timetable']) {
                continue;
            }

            /* Otherwise process them as usual
             */
            foreach ($data['timetable'] as $day => $periods) {
                foreach ($periods as $period) {

                    /* Collate a cumulative definition as we preprocess
                     *
                     * As we process everyone's timetable we should have the maximum definition
                     * for this week across all student timetables which for now, I have presumed
                     * to be rectangular and consistent
                     */
                    $periodsInDays[$period['sequence']] = [
                        'name' => $period['name'],
                        'sequence' => $period['sequence'],
                        'start' => $period['start'],
                        'finish' => $period['finish'],
                    ];
                }
            }
        }

        return $periodsInDays;
    }
}
