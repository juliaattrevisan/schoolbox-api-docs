<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\CampusMapper;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Batchxml;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\TimetableCampus;
use Schoolbox\External\TimetableCampusMapper;
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
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable
 */
class Definition implements IStrategy
{

    /**
     * Temporarily here to denote the start and end of cycles
     * There appears to be no equivalent concept in Kamar
     */
    private const CYCLE_START = '2000-01-01 00:00:00';
    private const CYCLE_END = '2999-01-01 00:00:00';

    /**
     * Kamar has hardcoded 10 "slots" per day
     */
    private const MAX_PERIODS = 10;


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
     * @var Batchxml
     */
    private $batchxml;


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
     * @param Batchxml              $batchxml
     * @param CampusMapper          $campusMapper
     * @param TimetableCampusMapper $timetableCampusMapper
     * @param TimetableCycleMapper  $timetableCycleMapper
     * @param TimetablePeriodMapper $timetablePeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Batchxml $batchxml,
        CampusMapper $campusMapper,
        TimetableCampusMapper $timetableCampusMapper,
        TimetableCycleMapper $timetableCycleMapper,
        TimetablePeriodMapper $timetablePeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->batchxml = $batchxml;
        $this->campusMapper = $campusMapper;
        $this->timetableCampusMapper = $timetableCampusMapper;
        $this->timetableCycleMapper = $timetableCycleMapper;
        $this->timetablePeriodMapper = $timetablePeriodMapper;
    }


    /**
     * Find the most recent week
     *
     * @param array $cycles
     * @return SimpleXMLElement
     * @throws RuntimeException
     */
    private function findCurrentCycle(array $cycles): SimpleXMLElement
    {
        $n = \count($cycles);

        if (!$n) {
            throw new RuntimeException('No week nodes provided.');
        }

        $max = 0;

        for ($i = 0; $i < $n; $i++) {
            $cycle = $cycles[$i];

            if ((int)$cycle->xpath('@index')[0] > $max) {
                $max = $i;
            }
        }

        /** @var SimpleXMLElement $cycle */
        $cycle = $cycles[$max];

        return $cycle;
    }


    /**
     * Import data from xml into local schema
     */
    public function import(): void
    {
        if ($this->perform) {
            $this->timetableCycleMapper->getDatabase()->query("DELETE FROM {$this->timetableCycleMapper->getTable()}");
            $this->timetablePeriodMapper->getDatabase()->query("DELETE FROM {$this->timetablePeriodMapper->getTable()}");
        }

        $cycles = $this->batchxml->fetchXml('/SMSDirectoryData/timetables/timetable/week');

        /* MASSIVE CAVEAT
         * Assume there's one campus set up in schoolbox instance
         */
        $campuses = $this->campusMapper->findAll();

        if (!\count($campuses)) {
            throw new RuntimeException('No campuses found during xml import.');
        }

        $campus = $campuses->first();

        /* TODO: Find the most recent week, I guess?
         * This probably needs to be based on their <term> node instead though
         * I need to see an actual school's data to help decide how the tool is used
         */
        $cycle = $this->findCurrentCycle($cycles);

        /** @var TimetableCycle $timetableCycle */
        $timetableCycle = $this->timetableCycleMapper->createModel()
            ->setId($campus->getId())
            ->setTitle('Week ' . $cycle->xpath('@index')[0])
            ->setCycleLength(\count($cycle->xpath('days/day')))
            ->setDayLength(self::MAX_PERIODS)

            /* What is this?
             */
            ->setRegular(1);

        $days = $cycle->xpath('days');

        foreach ($days as $day) {
        }

//            $dayData = $this->findDays($cycle);
//
//            /** @var TimetableCycle $timetableCycle */
//            $timetableCycle = $this->timetableCycleMapper->createModel()
//                ->setId($campus->getId())
//                ->setTitle(trim((string)$cycle->Name))
//                ->setCycleLength(\count($cycle->xpath('Days/Day')))
//                ->setDayLength($dayData['maxPeriods'])
//
//                /* What is this?
//                 */
//                ->setRegular(1);
//
//            /** @var TimetableCampus $timetableCampus */
//            $timetableCampus = $this->timetableCampusMapper->createModel()
//                ->setCycle($timetableCycle)
//                ->setCampus($campus)
//                ->setStart(\Alaress_Date_Format::newDatabaseDate(self::CYCLE_START))
//                ->setFinish(\Alaress_Date_Format::newDatabaseDate(self::CYCLE_END))
//                ->setStartDay(1);
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
//            if ($this->perform) {
//                $this->timetableCampusMapper->save($timetableCampus);
//            }
//
//            /* Save all the periods and associate them with a cycle
//             */
//            /** @var array $days */
//            $days = $dayData['days'];
//
//            foreach ($days as $day => $periods) {
//
//                /** @var array $periods */
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


        if (!$this->perform) {
            $this->log->info('Planning finished.');
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
            $dayIndex = trim((string)$day->Day);

            /* Figure out which day has the most periods
             * Consider this value and scheme canonical
             */
            $numPeriods = \count($periods);

            if ($numPeriods > $struct['maxPeriods']) {
                $struct['maxPeriods'] = $numPeriods;
            }

            $struct['days'][$dayIndex] = [];

            /** @var TimetablePeriod $period */
            foreach ($periods as $period) {
                $timetablePeriod = $this->timetablePeriodMapper->createModel()
                    ->setTitle(trim((string)$period->Name))
                    ->setDay($dayIndex)
                    ->setSequence(trim((string)$period->Ordinal))
                    ->setStart(trim((string)$period->StartTime . ':00'))
                    ->setFinish(trim((string)$period->EndTime . ':00'));

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
