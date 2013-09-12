<?php
/**
 * Synchronise_Schoolbox_Nhs_Strategy_Timetable
 *
 * Currently the export uses only following columns:
 * * Day: maps to timetable_period.day
 * * Period: maps to timetable_period.sequence
 * * Teacher: maps to a code in users table, and creates entry in class_user?
 * * Room: isn't stored in schoolbox connector. It should be added.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\TimetableCampus;
use Schoolbox\External\TimetableCampusMapper;
use Schoolbox\External\TimetableCycle;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriod;
use Schoolbox\External\TimetablePeriodMapper;

class Synchronise_External_Nhs_Strategy_Timetable extends Synchronise_Base_Strategy_Timetable {

    protected $columns = array();

    protected $campus_id = 1;

    /**
     * @var Campus
     */
    protected $campus;

    /**
     * @var TimetableCycle
     */
    protected $cycle;

    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_External_Nhs_Verbose();
        $this->campus         = CampusMapper::getInstance()->findById($this->campus_id);
        $timetable_campus = null;
        try {
            $timetable_campus = TimetableCampusMapper::getInstance()->findCurrentTimetable($this->campus);
        }
        catch (Exception $e) {
            return 0;
        }
        if (!($timetable_campus instanceof TimetableCampus)) {
            return 0;
        }
        $this->cycle = $timetable_campus->getCycle();
    }

    /**
     * {@inheritdoc}
     */
    public function generateList() {
        $this->list = array();
        // Where's the CSV file? Let's say in students_csv for now.
        // Need a better method for loading students too.
        $fp = fopen(__DIR__.'/timetable.csv', 'r');
        $this->columns = fgetcsv($fp, null, ',', '"');
        while (($row = fgetcsv($fp, null, ',', '"')) !== false) {
            $list_row = array();
            foreach ($this->columns as $i => $column) {
                if (isset($column)) {
                    $list_row[$column] = isset($row[$i]) ? $row[$i] : null;
                }
            }

            // Group by class
            $class = $list_row['Class'];
            $this->list[$class]['class']         = $class;
            $this->list[$class]['period_list'][] = array(
                'day'     => $list_row['Day'],
                'period'  => $list_row['Period'],
                'teacher' => $list_row['Teacher'],
                'room'    => $list_row['Room'],
            );
        }

        fclose($fp);
    }

    /**
     * Converts the synced item into a format that executeCreateUpdate() can
     * understand.
     *
     * @param  array $list_item
     * @return array
     */
    public function setupSyncedItemDetails($list_item) {
        return $list_item;
    }

    /**
     * Runs the create/update script for this class.
     *
     * @param  array $new_item
     * @return int
     */
    public function executeCreateUpdate($new_item) {

        // Timetable cycle:
        // ID: whatever
        // Title: whatever
        // cycle_length: max(day)
        // day_length: max(period)
        // regular: let's say True
        // You would likely have one per term.

        // Timetable campus:
        // cycle_id, campus_id: obvious
        // start: whenever their timetable starts!
        // reset: ?
        // offset: I'm saying 0
        // finish: end of the universe

        // Timetable:
        // There's only one campus for NHS, so everything goes into that one.
        // They don't have a start or an end, either.
        // cycle_id: whatever the current cycle is
        // title: lookup class name
        // day, sequence: from CSV (Day and Period)
        // start, finish: guess from Period
        // usable: I'm going to say True
        // room: doesn't exist in Schoolbox table, but should be added
        // teacher: should probably be stored as well, but isn't.

        $class = null;
        try {
            $class = ClassEntryMapper::getInstance()->findOneByColumn('code', $new_item['class']);
        }
        catch (Exception $e) {
            return 0;
        }
        if (!($class instanceof ClassEntry)) {
            return 0;
        }
//
//        $timetable_campus = null;
//        try {
//            $timetable_campus = TimetableCampusMapper::getInstance()->findCurrentTimetable($this->campus);
//        }
//        catch (Exception $e) {
//            return 0;
//        }
//        if (!($timetable_campus instanceof TimetableCampus)) {
//            return 0;
//        }
//
//        $cycle = $timetable_campus->getCycle();
//        $campus = $timetable_campus->getCampus();



        // First delete all classes for this cycle and campus.
        // Then restore them.
        TimetablePeriodMapper::getInstance()->deleteForClassAndCycle($class, $this->cycle);

        printf(
            "Setting %d periods for class \"%s\"\n",
            count($new_item['period_list']),
            $new_item['class']
        );
        foreach ($new_item['period_list'] as $period) {
            $sequence         = $period['period'];
            $timetable_period = new TimetablePeriod();
            // Guess at start/finish
            // for now, it can be: start: 8+period, finish: 9+period
            $start  = new DateTime(8+$sequence.':00');
            $finish = new DateTime(9+$sequence.':00');
            $name   = trim($class->getName());
            $code   = trim($class->getCode());
            $title  = '';
            if (strlen($name) > 0 && strlen($code) > 0) {
                $title = sprintf('%s (%s)', $name, $class->getCode());
            }
            elseif (strlen($name) > 0) {
                $title = $name;
            }
            elseif (strlen($code) > 0) {
                $title = $code;
            }
            $timetable_period
                ->setCycle($this->cycle)
                ->setClass($class)
                ->setTitle($title)
                ->setDay($period['day'])
                ->setSequence($sequence)
                ->setUsable(true)
                ->setStart($start)
                ->setFinish($finish)
            ;
            TimetablePeriodMapper::getInstance()->save($timetable_period);

        }
        return 1;
    }
}
