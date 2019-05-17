<?php
/**
 * Timetable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Isams\Base\Importer;

use Psr\Log\LoggerInterface;
use Schoolbox\External\TimetableCycle;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriod;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\External\UPS\Batchxml;

class Timetable implements ImporterInterface
{

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
    private $api;


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
        $this->api = $batchxml;
        $this->timetableCycleMapper = $timetableCycleMapper;
        $this->timetablePeriodMapper = $timetablePeriodMapper;
    }

    /**
     * Import data from xml into local schema
     */
    public function import(): void
    {
        $this->timetableCycleMapper->getDatabase()->query("DELETE FROM {$this->timetableCycleMapper->getTable()}");
        $this->timetablePeriodMapper->getDatabase()->query("DELETE FROM {$this->timetablePeriodMapper->getTable()}");

        $cycles = $this->api->fetchXml('/iSAMS/TimetableManager/Structure/Week');

        foreach ($cycles as $cycle) {
            $dayData = $this->findDays($cycle);

            /** @var TimetableCycle $timetableCycle */
            $timetableCycle = $this->timetableCycleMapper->createModel()
                ->setId((string)$cycle->xpath('@Id')[0])
                ->setTitle((string)$cycle->Name)
                ->setCycleLength(\count($cycle->xpath('Days/Day')))
                ->setDayLength($dayData['maxPeriods'])

                /* What is this?
                 */
                ->setRegular(1);

            if ($this->perform) {
                $this->timetableCycleMapper->save($timetableCycle);
            }

            $this->log->info(sprintf(
                'Creating timetable cycle %s with day length %s',
                $timetableCycle->getId(),
                $timetableCycle->getDayLength()
            ));

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
                        'Creating period %s (%s-%s) in timetable cycle %s',
                        $period->getTitle(),
                        $period->getStart(),
                        $period->getFinish(),
                        $timetableCycle->getId()
                    ));

                    if ($this->perform) {
                        $this->timetablePeriodMapper->save($period);
                    }
                }
            }
        }

        if (!$this->perform) {
            $this->log->info('Planning finished.');
        }
    }


    /**
     * Find all the Days in a given $cycle and the longest Day of them all
     *
     * @param \SimpleXMLElement $cycle
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    private function findDays(\SimpleXMLElement $cycle): array
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

            /* Figure out which day has the most periods
             * Consider this value and scheme canonical
             */
            $numPeriods = \count($periods);

            if ($numPeriods > $struct['maxPeriods']) {
                $struct['maxPeriods'] = $numPeriods;
            }

            $struct['days'][(string)$day->Day] = [];

            /** @var TimetablePeriod $period */
            foreach ($periods as $period) {
                $timetablePeriod = $this->timetablePeriodMapper->createModel()
                    ->setTitle((string)$period->Name)
                    ->setDay((string)$day->Day)
                    ->setSequence((string)$period->Ordinal)

                    /* Is this 12 or 24 hour time? Only seen morning values so far
                     */
                    ->setStart((string)$period->StartTime . ':00')
                    ->setFinish((string)$period->EndTime . ':00');

                $struct['days'][(string)$day->Day][] = $timetablePeriod;
            }
        }

        return $struct;
    }
}