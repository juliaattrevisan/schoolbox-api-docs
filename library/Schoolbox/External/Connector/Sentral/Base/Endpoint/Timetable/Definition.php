<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\Timetable;

use Psr\Log\LoggerInterface;
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
     * @var DefinitionCompiler
     */
    private $definitionCompiler;


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
     * @param DefinitionCompiler    $definitionCompiler
     * @param TimetableCycleMapper  $timetableCycleMapper
     * @param TimetablePeriodMapper $timetablePeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        DefinitionCompiler $definitionCompiler,
        TimetableCycleMapper $timetableCycleMapper,
        TimetablePeriodMapper $timetablePeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->definitionCompiler = $definitionCompiler;
        $this->timetableCycleMapper = $timetableCycleMapper;
        $this->timetablePeriodMapper = $timetablePeriodMapper;
    }


    /**
     * Import data from xml into local schema
     */
    public function import(): void
    {

        /* $ext is a key to identify this cycle and associate it with an luCampus record
         */
        $ext = self::CYCLE_IDENTIFIER;

        /* I've only seen one cycle so far
         */
        $cycleName = self::CYCLE_IDENTIFIER;
        $cycleLength = self::CYCLE_LENGTH;
        $cycleStart = \Alaress_Date_Format::newDatabaseDate(self::CYCLE_START);
        $cycleEnd = \Alaress_Date_Format::newDatabaseDate(self::CYCLE_END);
        $dayStart = 1;

        /* Find the longest day (might be excessive, can't tell yet)
         */
        $periodsInDays = $this->definitionCompiler->compile();
        $dayLength = 0;

        foreach ($periodsInDays as $date => $periods) {
            $currentLength = \count($periods);

            if ($dayLength < $currentLength) {
                $dayLength = $currentLength;
            }
        }

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
        $dayData = $this->findDays($dayLength, $periodsInDays);

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
     * @param int   $dayLength
     * @param array $periodsInDays
     * @return array
     * @throws Exception
     */
    private function findDays(int $dayLength, array $periodsInDays): array
    {
        $struct = [

            /* Each cycle has the potential for different Period structs
             * We only support a rectangular representation (every day must have the same number of periods)
             * Find the day with the most number of periods and assume that to be canonical
             * (some days have no period data)
             */
            'maxPeriods' => $dayLength,

            /* Store all the periods here, indexed by Day
              */
            'days' => []
        ];

        /* Explore all the Period data
         *
         * $periodsInDays is in an chronologically-ordered list;
         * it seems I can take advantage of that
         */
        $periods = array_values($periodsInDays)[0];

        for ($i = 0; $i < self::CYCLE_LENGTH; $i++) {
            $dayIndex = $i + 1;

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

            /* Each $period is an array of [name, order, startTime, endTime, isActive]
             */
            for ($j = 0; $j < $dayLength; $j++) {
                /* Not all days have the same number of periods
                 * The longest day we have seen might be 12 periods long
                 *
                 * But:
                 * Day 1 might have 10 periods
                 * Day 3 might have 6 periods
                 *
                 * Skip undefined periods
                 */
                if (!isset($periods[$j])) {
                    continue;
                }

                $period = $periods[$j];

                $periodName = $period['name'];
                $sequence = $period['order'];
                $start = $period['startTime'];
                $finish = $period['endTime'];

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
}
