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
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassPeriod;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\TimetableCampusMapper;
use Schoolbox\External\TimetableCycle;
use Schoolbox\External\TimetableCycleMapper;
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

        // Get the cycle for this campus.
        $timetable_campus = TimetableCampusMapper::getInstance()->findCurrentTimetable($this->campus);
        $this->cycle = $timetable_campus->getCycle();
    }

    /**
     * {@inheritdoc}
     */
    public function generateList() {
        $this->list = array();
        // Where's the CSV file? Let's say in students_csv for now.
        // Need a better method for loading students too.
        $reader = new Alaress\Csv\Reader(__DIR__.'/timetable.csv');
        $reader->setLoadFromFirst(true);
        foreach ($reader as $row) {
            // Group by class
            $class = $row['Class'];
            $this->list[$class]['class']         = $class;
            $this->list[$class]['period_list'][] = array(
                'day'     => $row['Day'],
                'period'  => $row['Period'],
                'teacher' => $row['Teacher'],
                'room'    => $row['Room'],
            );
        }
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

        // First delete all classes for this cycle and campus.
        // Then restore them.
        printf(
            "Setting %d periods for class \"%s\"\n",
            count($new_item['period_list']),
            $new_item['class']
        );
        foreach ($new_item['period_list'] as $period) {
            $class_period = new ClassPeriod();
            // Period: whatever the period for this cycle, day and sequence is
            $period_list = TimetablePeriodMapper::getInstance()->findByColumnList(
                array(
                    'cycle_id' => $this->cycle->getId(),
                    'day'      => $period['day'],
                    'sequence' => $period['period'],
                )
            );
            foreach ($period_list as $period_object) {
                ClassPeriodMapper::getInstance()->deleteForClassAndPeriod($class, $period_object);
                $class_period
                    ->setClass($class)
                    ->setPeriod($period_object)
                    ->setRoom($period['room'])
                ;
                // Attach teacher
                $teacher = null;
                try {
                    $teacher = UserMapper::getInstance()->findOneByColumn(
                        'synergy_id', $period['teacher']
                    );
                }
                catch (Exception $e) {}
                if ($teacher instanceof User) {
                    $class_period->setTeacher($teacher);
                }
                ClassPeriodMapper::getInstance()->save($class_period);
                // Break because we only want one row per period.
                break;
            }
        }
        return 1;
    }
}
