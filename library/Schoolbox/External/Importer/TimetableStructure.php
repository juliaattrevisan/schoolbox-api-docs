<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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

/**
 * Class TimetableStructure
 *
 * Importer for timetable structure into the Schoolbox connector.
 * Saves into the following tables:
 * * timetable_cycle
 * * sis_timetable_campus
 * * timetable_period
 *
 * @package Schoolbox\External\Importer
 */
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
            'Term Campus'           => 'The campus to create a term for (must correspond to a campus code in Schoolbox)',
            'Term Title'            => 'The name of the term',
            'Term Start'            => 'The date on which the term starts',
            'Term Finish'           => 'The date on which the term finishes',
            'Term Start Day Number' => 'The day number that "Term Start" is',
            'Period Day'            => 'The day number that this period is on',
            'Period Title'          => 'The name of this period',
            'Period Start'          => 'The time at which this period starts',
            'Period Finish'         => 'The time at which this period finishes',
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
                if (!$campusObject instanceof Campus) {
                    $this->log->warning(sprintf('Campus "%s" not found: skipping!', $termCampus));
                    continue;
                }

                // Timetable cycle - calculate cycle and day length later
//                $cycle = $this->timetableCycleMapper->findOneByColumn('title', $termTitle);
                $cycle = new TimetableCycle();
                $cycle
                    ->setTitle($termTitle)
                    ->setRegular(true);

                // Timetable campus
//                $campus = $this->timetableCampusMapper->findOneByColumn('cycle_id', $cycle->getId());
                $campus = new TimetableCampus();
                if (isset($row['Term Offset']) && strlen($row['Term Offset'])) {
                    $this->log->warning(
                        sprintf(
                            '"Term Offset" is deprecated - use "Term Start Day Number" instead. (Assuming value "%d" is start day "%d")',
                            $row['Term Offset'],
                            $row['Term Offset'] + 1
                        )
                    );
                    $campus->setStartDay($row['Term Offset'] + 1);
                }

                $termStart  = null;
                $termFinish = null;
                try {
                    $termStart  = new Alaress_Date_Format(
                        $row['Term Start'],
                        [Alaress_Date_Format::DATABASE_DATE_FORMAT]
                    );
                    $termFinish = new Alaress_Date_Format(
                        $row['Term Finish'],
                        [Alaress_Date_Format::DATABASE_DATE_FORMAT]
                    );
                } catch (\InvalidArgumentException $e) {

                }
                if (!($termStart instanceof Alaress_Date_Format && $termStart->isValid()) ||
                    !($termFinish instanceof Alaress_Date_Format && $termFinish->isValid())) {
                    $this->log->warning('"Term Start" and "Term Finish" must both be dates in YYYY-MM-DD format: skipping!');
                    continue;
                }

                $campus
                    ->setCycle($cycle)
                    ->setCampus($campusObject)
                    ->setStart($termStart)
                    ->setFinish($termFinish)
                    ->setStartDay($row['Term Start Day Number']);

                $result[$key] = [
                    'cycle'   => $cycle,
                    'campus'  => $campus,
                    'periods' => [],
                ];
            } else {
                $this->log->info(sprintf('Term "%s" for campus "%s" has been parsed', $termTitle, $termCampus));
            }

            // Sort the periods by start then end later, then use that to apply a sequence
            $day   = $row['Period Day'];
            $title = $row['Period Title'];
//            $period = $this->timetablePeriodMapper->findOneByColumnList([
//                'cycle_id' => $result[$key]['cycle']->getId(),
//                'day'      => $day,
//                'title'    => $title,
//            ]);
            $period = new TimetablePeriod();
            $this->log->info(sprintf('Parsing period "%s" for day %d', $title, $day));

            $periodStart = null;
            $periodFinish = null;

            try {
                if (empty($row['Period Start'])) {
                    $this->log->notice(
                        sprintf('Period "%s" for day %d missing "Period Start"', $title, $day),
                        ['row' => $row]);
                } else {
                    $periodStart = new Alaress_Date_Format($row['Period Start'], ['G:i:sa', 'H:i:s', 'G:ia', 'H:i']);
                }
                if (empty($row['Period Finish'])) {
                    $this->log->notice(
                        sprintf('Period "%s" for day %d missing "Period Finish"', $title, $day),
                        ['row' => $row]);
                } else {
                    $periodFinish = new Alaress_Date_Format($row['Period Finish'], ['G:i:sa', 'H:i:s', 'G:ia', 'H:i']);
                }
            } catch (\InvalidArgumentException $e) {

            }

            if (!($periodStart instanceof Alaress_Date_Format && $periodStart->isValid()) ||
                !($periodFinish instanceof Alaress_Date_Format && $periodFinish->isValid())) {
                $this->log->warning('"Period Start" and "Period Finish" must both be times in 12 hour (1:00pm or 1:00:00pm) or 24 hour (13:00 or 13:00:00) format: skipping!');
                continue;
            }

            $period
                ->setCycle($result[$key]['cycle'])
                ->setDay($day)
                ->setTitle($title)
                ->setStart($periodStart)
                ->setFinish($periodFinish)
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
            /** @var TimetableCampus $campus */
            $campus = $timetable['campus'];
            $this->log->info(
                sprintf(
                    'Calculating day and period count for term "%s" / campus "%s"',
                    $timetable['cycle']->getTitle(),
                    $campus->getCampus()->getExtTimetableCode()
                )
            );
            $dayCount    = max(array_keys($timetable['periods']));
            $periodNames = [];
            foreach ($timetable['periods'] as $day => $periods) {
                /** @var Collection|TimetablePeriod[] $periods */
                foreach ($periods as $period) {
                    $periodNames[$period->getTitle()] = true;
                }
            }
            $periodCount = count($periodNames);
            $this->log->info(sprintf('Day count: %d, period count: %d', $dayCount, $periodCount));
            $timetable['cycle']->setCycleLength($dayCount)->setDayLength($periodCount);

            // The campus start date no longer needs to be set into the past.
            // (This was added because LGSPK's timetables started in the future
            //  and I needed to test their timetables.
            //  Now, their timetable should be guaranteed to start in the past.)
//            if ($campus->getStart()->isFuture()) {
//                $this->log->info(sprintf('Timetable cycle is starting in future - offsetting so it starts in past'));
//                $i = 0;
//                while ($campus->getStart()->isFuture() && $i++ < 100) {
//                    // Go back $dayCount weekdays until $start is not in future
//                    $campus->getStart()->addWeekdays(-$dayCount);
//                }
//                $this->log->info(sprintf('Timetable cycle now starts on "%s"', $campus->getStart()->getDatabaseDate()));
//            }

            // Sort periods by day, and then set period sequence that way
            $this->log->info('Setting sequence for periods');
            foreach ($timetable['periods'] as $day => $periods) {

                // For any period on this day, if period start or finish not defined, retain CSV ordering.
                $isMissingTimes = false;
                foreach ($periods as $period) {
                    if (empty($period->getStart()) || empty($period->getFinish())) {
                        $isMissingTimes = true;
                        break;
                    }
                }
                if (!$isMissingTimes) {
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
                }

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
    public
    function save()
    {
        // Eventual override rules:
        // * Term: same title/campus combination
        // * Period: same term, and period title/day combination
        if ($this->performUpdate) {
            $this->log->notice('Cleaning up all existing cycles');
            $this->timetableCycleMapper->truncate();
            $this->log->notice('Cleaning up all existing campus cycles');
            $this->timetableCampusMapper->truncate();
            $this->log->notice('Cleaning up all existing periods');
            $this->timetablePeriodMapper->truncate();
        }
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
                    $this->log->info(sprintf('Campus cycle saved (Cycle ID: #%d)', $campus->getCycle()->getId()));
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
