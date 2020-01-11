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
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\TimetableCampus;
use Schoolbox\External\TimetableCampusMapper;
use Schoolbox\External\TimetableCycle;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriod;
use Schoolbox\External\TimetablePeriodMapper;

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
     * There appears to be five days per cycle
     */
    private const DAYS_PER_CYCLE = 5;


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
     * MASSIVE CAVEAT
     * Assume there's one campus set up in schoolbox instance
     *
     * @throws RuntimeException
     */
    private function findFirstCampus(): Campus
    {
        $campuses = $this->campusMapper->findAll();

        if (!\count($campuses)) {
            throw new RuntimeException('No campuses found during xml import.');
        }

        return $campuses->first();
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

        $campus = $this->findFirstCampus();

        /** @var TimetableCycle $timetableCycle */
        $timetableCycle = $this->timetableCycleMapper->createModel()
            ->setId($campus->getId())
            ->setTitle('Week ' . date('W'))
            ->setCycleLength(self::DAYS_PER_CYCLE)
            ->setDayLength(self::MAX_PERIODS)

            /* What is this?
             */
            ->setRegular(1);

        /** @var TimetableCampus $timetableCampus */
        $timetableCampus = $this->timetableCampusMapper->createModel()
            ->setCycle($timetableCycle)
            ->setCampus($campus)
            ->setStart(\Alaress_Date_Format::newDatabaseDate(self::CYCLE_START))
            ->setFinish(\Alaress_Date_Format::newDatabaseDate(self::CYCLE_END))
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
        for ($i = 1; $i <= self::DAYS_PER_CYCLE; $i++) {
            for ($j = 1; $j <= self::MAX_PERIODS; $j++) {

                /** @var TimetablePeriod $timetablePeriod */
                $timetablePeriod = $this->timetablePeriodMapper->createModel()
                    ->setTitle("Period {$j}")
                    ->setDay($i)
                    ->setSequence($j)
                    ->setStart(null)
                    ->setFinish(null)
                    ->setCycle($timetableCycle);

                $this->log->info(sprintf(
                    'Creating period %s (%s-%s) for day %s in timetable cycle %s (%s) for campus %s',
                    $timetablePeriod->getTitle(),
                    $timetablePeriod->getStart(),
                    $timetablePeriod->getFinish(),
                    $timetablePeriod->getDay(),
                    $timetableCycle->getId(),
                    $timetableCycle->getTitle(),
                    $campus->getName()
                ));

                if ($this->perform) {
                    $this->timetablePeriodMapper->save($timetablePeriod);
                }
            }
        }
    }
}
