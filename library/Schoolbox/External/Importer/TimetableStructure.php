<?php
/**
 * Importer for timetable structure into the Schoolbox connector.
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
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\External\TimetableCampus;
use Schoolbox\External\TimetableCampusMapper;
use Schoolbox\External\TimetableCycle;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriod;
use Schoolbox\External\TimetablePeriodMapper;

class TimetableStructure extends AbstractImporter
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
     * Documentation function for expected format
     *
     * @return array
     */
    public static function getFormat()
    {
        return [
            'Term Campus'   => 'The campus to create a term for (must correspond to a campus code in Schoolbox)',
            'Term Title'    => 'The name of the term',
            'Term Start'    => 'The date on which the term starts',
            'Term Finish'   => 'The date on which the term finishes',
            'Term Offset'   => 'The day number that "Term Start" is',
            'Period Day'    => 'The day number that this period is on',
            'Period Title'  => 'The name of this period',
            'Period Start'  => 'The time at which this period starts',
            'Period Finish' => 'The time at which this period finishes',
        ];
    }

    /**
     * Custom setup function
     */
    protected function setup()
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
     * @return array
     */
    protected function parse()
    {
        $result = [];
        foreach ($this->reader as $i => $row) {
            $this->log->info("Parsing row $i...");
            if (!isset($row['Term Campus']) || !isset($row['Term Title'])) {
                $this->log->warning('Mandatory fields "Term Campus" and "Term Title" not defined: skipping!');
                continue;
            }

            $termTitle  = $row['Term Title'];
            $termCampus = $row['Term Campus'];
            $key        = sprintf('%s-%s', $termCampus, $termTitle);
            if (!isset($result[$key])) {
                $this->log->info(sprintf('Term "%s" for campus "%s" has not been parsed: parsing now', $termTitle, $termCampus));
                $campusObject = $this->campusList->getBy('extTimetableCode', $termCampus);
                if (!$campusObject) {
                    $this->log->warning(sprintf('Campus "%s" not found: skipping!', $termCampus));
                    continue;
                }

                // Timetable cycle - calculate cycle and day length later
                $cycle = new TimetableCycle();
                $cycle
                    ->setTitle($termTitle)
                    ->setRegular(true);

                // Timetable campus
                $campus = new TimetableCampus();
                $campus
                    ->setCycle($cycle)
                    ->setCampus($campusObject)
                    ->setStart(new Alaress_Date_Format($row['Term Start'], Alaress_Date_Format::DATABASE_DATE_FORMAT))
                    ->setFinish(new Alaress_Date_Format($row['Term Finish'], Alaress_Date_Format::DATABASE_DATE_FORMAT))
                    ->setOffset($row['Term Offset']);

                $result[$key] = [
                    'cycle'   => $cycle,
                    'campus'  => $campus,
                    'periods' => [],
                ];
            } else {
                $this->log->info(sprintf('Term "%s" for campus "%s" has been parsed', $termTitle, $termCampus));
            }

            // Sort the periods by start then end later, then use that to apply a sequence
            $day    = $row['Period Day'];
            $title  = $row['Period Title'];
            $period = new TimetablePeriod();
            $this->log->info(sprintf('Parsing period "%s" for day %d', $title, $day));
            $period
                ->setCycle($result[$key]['cycle'])
                ->setDay($day)
                ->setStart(new Alaress_Date_Format($row['Period Start'], Alaress_Date_Format::DATABASE_TIME_FORMAT))
                ->setFinish(new Alaress_Date_Format($row['Period Finish'], Alaress_Date_Format::DATABASE_TIME_FORMAT))
                ->setTitle($row['Period Title'])
                ->setUsable(true);
            if (!isset($result[$key]['periods'][$day])) {
                $result[$key]['periods'][$day] = new Collection();
            }
            $result[$key]['periods'][$day]->add($period);
            $this->log->info(sprintf('Period "%s" for day %d has been parsed', $title, $day));
        }
        $this->log->info("All rows parsed - calculating cycle and day lengths and period sequences");

        // Sort the period list, and set sequence based on that
        foreach ($result as $key => $timetable) {
            $this->log->info(
                sprintf(
                    'Calculating day and period count for term "%s" / campus "%s"',
                    $timetable['cycle']->getTitle(),
                    $timetable['campus']->getCampus()->getExtTimetableCode()
                )
            );
            $dayCount    = max(array_keys($timetable['periods']));
            $periodCount = max(array_map('count', $timetable['periods']));
            $this->log->info(sprintf('Day count: %d, period count: %d', $dayCount, $periodCount));
            $timetable['cycle']->setCycleLength($dayCount)->setDayLength($periodCount);

            // Sort periods by day, and then set period sequence that way
            $this->log->info('Setting sequence for periods');
            foreach ($timetable['periods'] as $day => $periods) {
                /** @var Collection|TimetablePeriod[] $periods */
                $periods->sortByCallback(
                    function ($a, $b) {
                        /** @var TimetablePeriod $a */
                        /** @var TimetablePeriod $b */
                        if (($cmp = $a->getStart()->compare($b->getStart())) !== 0) {
                            return $cmp;
                        }
                        return $a->getFinish()->compare($b->getFinish());
                    }
                );
                $i = 1;
                foreach ($periods as $period) {
                    $this->log->info(
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
        $this->log->info("Parsing completed");

        // Mangle all of this into a list of timetable cycles and stuff
        return $result;
    }

    /**
     * Reads the rows to insert from the CSV, and writes to the database.
     */
    public function save()
    {
        // Eventual override rules:
        // * Term: same title/campus combination
        // * Period: same term, and period title/day combination
        foreach ($this->parse() as $row) {
            /** @var TimetableCycle $cycle */
            $cycle = $row['cycle'];
            if ($this->performUpdate) {
                $this->log->info(sprintf('Saving cycle for term "%s"', $cycle->getTitle()));
                if ($this->timetableCycleMapper->save($cycle)) {
                    $this->log->info(sprintf('Cycle saved (ID: #%d)', $cycle->getId()));
                } else {
                    $this->log->error('Could not save cycle!');
                }
            } else {
                $this->log->info(sprintf('Pretending to save cycle for term "%s"', $cycle->getTitle()));
            }

            /** @var TimetableCampus $campus */
            $campus = $row['campus'];
            $code   = $campus->getCampus()->getExtTimetableCode();
            if ($this->performUpdate) {
                $this->log->info(sprintf('Saving campus cycle for campus "%s"', $code));
                if ($this->timetableCampusMapper->save($campus)) {
                    $this->log->info('Campus cycle saved (Cycle ID: #%d)', $campus->getCycle()->getId());
                } else {
                    $this->log->error('Could not save campus cycle!');
                }
            } else {
                $this->log->info(sprintf('Pretending to save campus cycle for campus "%s"', $code));
            }

            foreach ($row['periods'] as $day => $periods) {
                /** @var Collection|TimetablePeriod[] $periods */
                foreach ($periods as $period) {
                    if ($this->performUpdate) {
                        $this->log->info(sprintf('Saving period "%s" for day %d', $period->getTitle(), $period->getDay()));
                        if ($this->timetablePeriodMapper->save($period)) {
                            $this->log->info(sprintf('Period saved (ID: #%d)', $period->getId()));
                        } else {
                            $this->log->error('Could not save period!');
                        }
                    } else {
                        $this->log->info(sprintf('Pretending to save period "%s" for day %d', $period->getTitle(), $period->getDay()));
                    }
                }
            }
        }
    }
}
