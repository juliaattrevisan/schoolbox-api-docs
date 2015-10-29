<?php
/**
 * Importer for timetable structure into the Schoolbox connector.
 *
 * Saves into the following tables:
 * * timetable_cycle
 * * timetable_campus
 * * timetable_period
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Importer;

use Alaress_Date_Format;
use Alaress\Csv\Reader;
use Psr\Log\LoggerInterface;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\External\TimetableCampus;
use Schoolbox\External\TimetableCampusMapper;
use Schoolbox\External\TimetableCycle;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriod;
use Schoolbox\External\TimetablePeriodMapper;

class TimetableStructure
{
    /**
     * @var Collection|Campus[]
     */
    protected $campusList;

    /**
     * @var TimetableCycleMapper
     */
    protected $timetableCycleMapper;

    /**
     * @var TimetableCampusMapper
     */
    protected $timetableCampusMapper;

    /**
     * @var TimetablePeriodMapper
     */
    protected $timetablePeriodMapper;

    /**
     * Constructor
     */
    public function __construct()
    {
        $campusMapper                = new CampusMapper();
        $this->campusList            = $campusMapper->findAll();
        $this->timetableCycleMapper  = new TimetableCycleMapper();
        $this->timetableCampusMapper = new TimetableCampusMapper();
        $this->timetablePeriodMapper = new TimetablePeriodMapper();
    }

    /**
     * Reads all rows from the CSV reader, and loads them into an associative
     * array structure.
     *
     * @param Reader          $reader
     * @param LoggerInterface $log
     *
     * @return array
     */
    protected function parse(Reader $reader, LoggerInterface $log)
    {
        $result = [];
        $reader->setLoadFromFirst(true)->setFillDown(true);
        foreach ($reader as $i => $row) {
            $log->info("Parsing row $i...");
            if (!isset($row['Term Campus']) || !isset($row['Term Title'])) {
                $log->warning('Mandatory fields "Term Campus" and "Term Title" not defined: skipping!');
                continue;
            }

            $termTitle  = $row['Term Title'];
            $termCampus = $row['Term Campus'];
            $key = sprintf('%s-%s', $termCampus, $termTitle);
            if (!isset($result[$key])) {
                $log->info(sprintf('Term "%s" for campus "%s" has not been parsed: parsing now', $termTitle, $termCampus));
                $campusObject = $this->campusList->getBy('extTimetableCode', $termCampus);
                if (!$campusObject) {
                    $log->warning(sprintf('Campus "%s" not found: skipping!', $termCampus));
                    continue;
                }

                // Timetable cycle - calculate cycle and day length later
                $cycle = new TimetableCycle();
                $cycle
                    ->setTitle($termTitle)
                    ->setRegular(true)
                ;

                // Timetable campus
                $campus = new TimetableCampus();
                $campus
                    ->setCycle($cycle)
                    ->setCampus($campusObject)
                    ->setStart(new Alaress_Date_Format($row['Term Start'], Alaress_Date_Format::DATABASE_DATE_FORMAT))
                    ->setFinish(new Alaress_Date_Format($row['Term Finish'], Alaress_Date_Format::DATABASE_DATE_FORMAT))
                    ->setOffset($row['Term Offset'])
                ;

                $result[$key] = [
                    'cycle'   => $cycle,
                    'campus'  => $campus,
                    'periods' => [],
                ];
            } else {
                $log->info(sprintf('Term "%s" for campus "%s" has been parsed', $termTitle, $termCampus));
            }

            // Sort the periods by start then end later, then use that to apply a sequence
            $day    = $row['Period Day'];
            $title  = $row['Period Title'];
            $period = new TimetablePeriod();
            $log->info(sprintf('Parsing period "%s" for day %d', $title, $day));
            $period
                ->setCycle($result[$key]['cycle'])
                ->setDay($day)
                ->setStart(new Alaress_Date_Format($row['Period Start'], Alaress_Date_Format::DATABASE_TIME_FORMAT))
                ->setFinish(new Alaress_Date_Format($row['Period Finish'], Alaress_Date_Format::DATABASE_TIME_FORMAT))
                ->setTitle($row['Period Title'])
                ->setUsable(true)
            ;
            if (!isset($result[$key]['periods'][$day])) {
                $result[$key]['periods'][$day] = new Collection();
            }
            $result[$key]['periods'][$day]->add($period);
            $log->info(sprintf('Period "%s" for day %d has been parsed', $title, $day));
        }
        $log->info("All rows parsed - calculating cycle and day lengths and period sequences");

        // Sort the period list, and set sequence based on that
        foreach ($result as $key => $timetable) {
            $log->info(
                sprintf(
                    'Calculating day and period count for term "%s" / campus "%s"',
                    $timetable['cycle']->getTitle(),
                    $timetable['campus']->getCampus()->getExtTimetableCode()
                )
            );
            $dayCount    = max(array_keys($timetable['periods']));
            $periodCount = max(array_map('count', $timetable['periods']));
            $log->info(sprintf('Day count: %d, period count: %d', $dayCount, $periodCount));
            $timetable['cycle']->setCycleLength($dayCount)->setDayLength($periodCount);

            // Sort periods by day, and then set period sequence that way
            $log->info('Setting sequence for periods');
            foreach ($timetable['periods'] as $day => $periods) {
                /** @var Collection|TimetablePeriod[] $periods */
                $periods->sortByCallback(function($a, $b) {
                    /** @var TimetablePeriod $a */
                    /** @var TimetablePeriod $b */
                    if (($cmp = $a->getStart()->compare($b->getStart())) !== 0) {
                        return $cmp;
                    }
                    return $a->getFinish()->compare($b->getFinish());
                });
                $i = 1;
                foreach ($periods as $period) {
                    $log->info(
                        sprintf(
                            'Period "%s" for day %d has sequence %d',
                            $period->getTitle(),
                            $period->getDay(),
                            $day,
                            $i
                        )
                    );
                    $period->setSequence($i++);
                }
            }
        }
        $log->info("Parsing completed");

        // Mangle all of this into a list of timetable cycles and stuff
        return $result;
    }

    /**
     * Reads the rows to insert from the CSV, and writes to the database.
     *
     * @param Reader          $reader
     * @param LoggerInterface $log
     */
    public function save(Reader $reader, LoggerInterface $log)
    {
        // Eventual override rules:
        // * Term: same title/campus combination
        // * Period: same term, and period title/day combination
        foreach ($this->parse($reader, $log) as $row) {
            /** @var TimetableCycle $cycle */
            $cycle = $row['cycle'];
            $log->info(sprintf('Saving cycle for term "%s"', $cycle->getTitle()));
            $this->timetableCycleMapper->save($cycle);

            /** @var TimetableCampus $campus */
            $campus = $row['campus'];
            $log->info(sprintf('Saving campus cycle for campus "%s"', $campus->getCampus()->getExtTimetableCode()));
            $this->timetableCampusMapper->save($campus);

            foreach ($row['periods'] as $day => $periods) {
                /** @var Collection|TimetablePeriod[] $periods */
                foreach ($periods as $period) {
                    $log->info(sprintf('Saving period "%s" for day %d', $period->getTitle(), $period->getDay()));
                }
                $this->timetablePeriodMapper->save($periods);
            }
        }
    }
}
