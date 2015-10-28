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
     * @var Reader
     */
    protected $csvReader;

    /**
     * @var CampusMapper
     */
    protected $campusMapper;

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

    public function __construct($filename)
    {
        $this->campusMapper          = new CampusMapper();
        $this->timetableCycleMapper  = new TimetableCycleMapper();
        $this->timetableCampusMapper = new TimetableCampusMapper();
        $this->timetablePeriodMapper = new TimetablePeriodMapper();
        $this->csvReader = new Reader($filename);
        $this->csvReader
            ->setLoadFromFirst(true)
            ->setFillDown(true)
        ;
    }

    public function parse()
    {
        $result = [];
        foreach ($this->csvReader as $row) {
            $key = sprintf('%s-%s', $row['Term Campus'], $row['Term Title']);
            if (!isset($result[$key])) {
                // Timetable cycle - calculate cycle and day length later
                $cycle = new TimetableCycle();
                $cycle
                    ->setTitle($row['Term Title'])
                    ->setRegular(true)
                ;

                // Timetable campus
                $campus = new TimetableCampus();
                $campus
                    ->setCampus($this->campusMapper->findByColumn('ext_timetable_code', $row['Term Campus']))
                    ->setStart(new Alaress_Date_Format($row['Term Start'], Alaress_Date_Format::DATABASE_DATE_FORMAT))
                    ->setFinish(new Alaress_Date_Format($row['Term Finish'], Alaress_Date_Format::DATABASE_DATE_FORMAT))
                    ->setOffset($row['Term Offset'])
                ;

                $result[$key] = [
                    'cycle'   => $cycle,
                    'campus'  => $campus,
                    'periods' => [],
                ];
            }

            // Sort the periods by start then end later, then use that to apply a sequence
            $day    = $row['Period Day'];
            $period = new TimetablePeriod();
            $period
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
        }

        // Sort the period list, and set sequence based on that
        foreach ($result as $key => $timetable) {
            $timetable['cycle']
                ->setCycleLength(max(array_keys($timetable['periods'])))
                ->setDayLength(max(array_map('count', $timetable['periods'])))
            ;
            // Sort periods by day, and then set period sequence that way
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
                    $period->setSequence($i++);
                }
            }
        }

        // Mangle all of this into a list of timetable cycles and stuff
        return $result;
    }

    public function save()
    {
        // Override, or leave untouched?
        // I think that this should always delete then reinsert,
        // because this is a less common import than timetable or enrolment.
        // We still need to keep previous year's enrolments though.
        foreach ($this->parse() as $row) {
            /** @var TimetableCycle $cycle */
            $cycle = $row['cycle'];
            $this->timetableCycleMapper->save($cycle);

            /** @var TimetableCampus $campus */
            $campus = $row['campus'];
            $campus->setCycle($cycle);
            $this->timetableCampusMapper->save($campus);

            foreach ($row['periods'] as $day => $periods) {
                /** @var Collection|TimetablePeriod[] $periods */
                foreach ($periods as $period) {
                    $period->setCycle($cycle);
                }
                $this->timetablePeriodMapper->save($periods);
            }
        }
    }
}
