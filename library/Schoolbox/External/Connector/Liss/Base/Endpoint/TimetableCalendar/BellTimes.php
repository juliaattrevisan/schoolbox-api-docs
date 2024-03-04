<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar;

use Alaress\Database\Predicate\InvalidColumnException;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Liss\Base\Endpoint\PayloadCache;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\TimetableCycle;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Mapper;

/**
 * Class BellTimes
 *
 * This fulfils the cyclic timetable grid of yesteryear via liss data.
 * There are a number of hardcoded assumptions here that have endured
 * since the dawn of liss, but few people use the cyclic grid anymore.
 * We encourage schools to use the schedule timetable instead.
 */
class BellTimes implements IStrategy
{
    /**
     * When TtStructure values are mysteriously empty, use this string
     * as the cycle's identifier and also its name. TtStructure is
     * sometimes provided in the input payload to distinguish cycles,
     * but in its absence we assume a default one instead.
     */
    private const DEFAULT_CYCLE_IDENTIFIER = 'Main Timetable';


    /**
     * A lookup of cycles
     *
     * @var TimetableCycle[]
     */
    private array $cycleCache = [];


    /**
     * Constructor.
     *
     * @param LoggerInterface $log Logs the things that happened, in a text file
     * @param bool $perform
     * @param FilesystemProxy $filesystemProxy
     * @param Mapper $storageMapper
     * @param TimetablePeriodMapper $periodMapper
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private \Schoolbox\Resource\Storage\FilesystemProxy $filesystemProxy,
        private \Schoolbox\Resource\Storage\Mapper $storageMapper,
        private TimetablePeriodMapper $periodMapper
    ) {
    }


    /**
     * Import into suds timetable
     *
     * @return void
     * @throws Exception
     * @throws InvalidColumnException
     * @throws \InvalidArgumentException
     * @throws FilesystemException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        /* Make all the data easier to manipulate by rearranging their guts
         */
        $message = (new PayloadCache($this->storageMapper, $this->filesystemProxy, $this->log))
            ->load(PayloadCache::PAYLOAD_BELLTIMES);

        if (null === $message) {
            $this->log->debug(sprintf(
                'No payloads for %s; skipping',
                PayloadCache::PAYLOAD_BELLTIMES
            ));
        }

        /* Index everything in a hash lookup, indexed hierarchically by cycle/day/period
         * It turns out schools have multiple cycles of varying day lengths
         */
        $lookup = [];

        $this->log->info('publishBellTimes: processing request');

        $allData = $message->getParam(2)->me['array'];

        /* Truncate everything before re-adding
         * Note: This was supposed to already have been done via publishDailyData
         * which is supposed to go first
         *
         * $timetablePeriodMapper->truncate();
         */
        foreach ($allData as $currentData) {
            $struct = $currentData->me['struct'];

            /* Use $currentData['TtStructure']'s value as the cycle identifier
             * and load that if it exists, creating a new one if it doesn't
             */
            $cycleIdentifier = trim((string)($struct['TtStructure']->me['string'] ?? null));
            $cycle = $this->getCycle($cycleIdentifier);

            $dayNumber = trim((string)($struct['DayNumber']->me['i4'] ?? null));
            $periodName = trim((string)($struct['Period']->me['string'] ?? null));
            $startTime = trim((string)($struct['StartTime']->me['string'] ?? null));
            $finishTime = trim((string)($struct['EndTime']->me['string'] ?? null));

            try {
                $timetablePeriod = $this->periodMapper->findOneByColumnList([
                    'cycle_id' => $cycle->getId(),
                    'title' => $periodName,
                    'day' => $dayNumber,
                    'start' => $startTime,
                    'finish' => $finishTime,
                ]);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $timetablePeriod = $this->periodMapper->createModel()
                    ->setCycle($cycle)
                    ->setTitle($periodName)
                    ->setDay($dayNumber)
                    ->setStart(new \Alaress_Date_Format($startTime, 'H:i'))
                    ->setFinish(new \Alaress_Date_Format($finishTime, 'H:i'));
            }

            /* TODO: Undo these hardcoded values at some point
              */
            $timetablePeriod->setUsable(1);

            /* Build a hash lookup of cycles, days and their periods
             */
            if (!isset($lookup[$cycle->getId()][$dayNumber])) {
                $lookup[$cycle->getId()][$dayNumber] = [];
            }

            $lookup[$cycle->getId()][$dayNumber][] = $timetablePeriod;
        }

        /* Now traverse $lookup and assign sequence
         * Take into account non-uniform day lengths by cross-referencing with $counts
         */
        foreach ($lookup as $cycleId => $dayData) {
            $this->log->info(sprintf(
                'publishBellTimes: Cycle %s has %s days',
                $cycleId,
                \count($dayData)
            ));

            foreach ($dayData as $dayIndex => $periods) {
                $this->log->info(sprintf(
                    'publishBellTimes: Cycle %s, day %s has %s periods',
                    $cycleId,
                    $dayIndex,
                    \count($periods)
                ));

                /* Apply sequencing to every period in this day in this cycle
                 */
                $i = 0;

                foreach ($periods as $timetablePeriod) {
                    $timetablePeriod->setSequence($i);

                    if ($this->perform) {
                        $this->periodMapper->save($timetablePeriod);
                    }

                    $i++;
                }
            }
        }

        $this->log->info(sprintf("publishBellTimes: Saved timetable periods, process completed"));
    }


    /**
     * Predefine a sis_timetable_cycle record (this doesn't seem defined/accessible
     * via liss, for some reason - it might be in TimetableStructure ttStructure
     * which is not accessible to us)
     *
     * @param string $identifier
     * @return TimetableCycle
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function getCycle(string $identifier): TimetableCycle
    {
        /* If the $identifier is somehow an empty string, then it might not have
         * been set properly in the remote data source - just assume the usual
         * traditional default identifier in this case, and use that
         */
        $cycleIdentifier = $identifier ?: self::DEFAULT_CYCLE_IDENTIFIER;

        /* Return a cycle from the existing cached cycles
         */
        if (isset($this->cycleCache[$cycleIdentifier])) {
            return $this->cycleCache[$cycleIdentifier];
        }

        /* Try to find a Timetable Cycle matching $identifier
         * If one exists, load it and reuse it
         * Otherwise create a new one
         */
        $cycleMapper = TimetableCycleMapper::getInstance();

        try {
            $cycle = $cycleMapper->findOneByColumn('cycle_identifier', $cycleIdentifier);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->log->warning(sprintf(
                "publishBellTimes: Did not find a cycle for TtStructure identifier %s; creating a new one for it instead",
                $cycleIdentifier
            ));

            /* Assume some basic ideas about this new cycle because we haven't
             * seen anything warranting changing them yet
             *      a) It starts on Day 1
             *      b) It has a 10-day cycle length
             *      c) Each day has 13 slots in it
             *      d) Goes for the full calendar year
             */
            $cycle = $cycleMapper->createModel()
                ->setTitle($cycleIdentifier)
                ->setCycleIdentifier($cycleIdentifier)
                ->setStart(\Alaress_Date_Format::now()->startOfYear())
                ->setFinish(\Alaress_Date_Format::now()->endOfYear())
                /* TODO: Undo these hardcoded values at some point
                 */
                ->setStartDay(1)
                ->setCycleLength(10)
                ->setDayLength(13);

            $cycleMapper->save($cycle);
        }

        $this->cycleCache[$cycleIdentifier] = $cycle;

        return $cycle;
    }
}
