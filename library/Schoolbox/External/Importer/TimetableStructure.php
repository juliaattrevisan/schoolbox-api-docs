<?php
/**
 * Importer for timetable structure into the Schoolbox connector.
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
use Schoolbox\External\TimetableCycle;
use Schoolbox\External\TimetablePeriod;

class TimetableStructure
{
    /**
     * @var Reader
     */
    protected $csvReader;

    public function __construct($filename)
    {
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
                    ->setCampus(CampusMapper::getInstance()->findByColumn('ext_timetable_code', $row['Term Campus']))
                    ->setStart(new Alaress_Date_Format($row['Term Start'], Alaress_Date_Format::DATABASE_DATE_FORMAT))
                    ->setFinish(new Alaress_Date_Format($row['Term Finish'], Alaress_Date_Format::DATABASE_DATE_FORMAT))
                    ->setOffset($row['Term Offset'])
                ;

                $result[$key] = [
                    'cycle'   => $cycle,
                    'campus'  => $campus,
                    'periods' => new Collection(),
                ];
            }

            // Sort the periods by start then end later, then use that to apply a sequence
            $period = new TimetablePeriod();
            $period
                ->setDay($row['Period Day'])
                ->setStart(new Alaress_Date_Format($row['Period Start'], Alaress_Date_Format::DATABASE_TIME_FORMAT))
                ->setFinish(new Alaress_Date_Format($row['Period Finish'], Alaress_Date_Format::DATABASE_TIME_FORMAT))
                ->setTitle($row['Period Title'])
                ->setUsable(true)
            ;
            $result[$key]['periods']->add($period);
        }

        // Mangle all of this into a list of timetable cycles and stuff
        return $result;
    }
}
