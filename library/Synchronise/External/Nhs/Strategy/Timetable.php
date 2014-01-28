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

    protected $location  = '';
    protected $columns   = array();
    protected $title     = "Update Schoolbox Timetable Details Synchronisation";
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
     * Sets up synchronisation with parameters passed from the definition.
     *
     * @param string $location Where the CSV for import is located
     * @param array  $columns  The columns to import
     */
    public function __construct($location, $columns) {
        parent::__construct();
        $this->location = $location;
        $this->columns  = $columns;
    }

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
        $reader = new Alaress\Csv\Reader($this->location);
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
        $class         = null;
        $class_message = sprintf('timetable for class "%s"', $new_item['class']);
        try {
            $class = ClassEntryMapper::getInstance()->findOneByColumn('code', $new_item['class']);
        }
        catch (Exception $e) {}
        if (!($class instanceof ClassEntry)) {

            if ($this->perform_update) {
                //Create this weird class we just found.
                $class = new ClassEntry();
                $class->setName($new_item['class']);
                $class->setCode($new_item['class']);
                ClassEntryMapper::getInstance()->save($class);
            }
        }

        $classInvolvements = array();

        // First delete all classes and involvements for this cycle and campus.
        // Then restore them.
        $timetable_message = count($new_item['period_list']).' period(s) for '.$class_message;
        if ($this->perform_update) {
            $this->verbose_filter->outputPerformUpdateMessage($timetable_message);
            foreach ($new_item['period_list'] as $period) {
                $class_period = new ClassPeriod();
                // Period: whatever the period for this cycle, day and sequence is
                $params = array(
                    'cycle_id' => $this->cycle->getId(),
                    'day'      => $period['day'],
                    'title'    => $period['period'],
                );
                $period_list = TimetablePeriodMapper::getInstance()->findByColumnList($params);

                // Get teacher for this class, and set it into class involvement
                // and period.
                $teacher = null;
                try {
                    $teacher = UserMapper::getInstance()->findOneByColumn(
                        'synergy_id', $period['teacher']
                    );
                    $class_period->setTeacher($teacher);
                }
                catch (Exception $e) {
                    $this->verbose_filter->outputMessage(
                        sprintf('Teacher "%s" not found for class "%s".', $period['teacher'], $class->getCode())
                    );
                    continue;
                }

                foreach ($period_list as $period_object) {
                    ClassPeriodMapper::getInstance()->deleteForClassAndPeriod($class, $period_object);
                    $class_period
                        ->setClass($class)
                        ->setPeriod($period_object)
                        ->setRoom($period['room'])
                    ;
                    // Attach teacher
                    $class_period->setTeacher($teacher);
                    ClassPeriodMapper::getInstance()->save($class_period);
                    // Break because we only want one row per period.
                    break;
                }
            }
        }
        else {
            $this->verbose_filter->outputPlanUpdateMessage($timetable_message);
        }
        $this->verbose_filter->incrementUpdated();
        return 1;
    }
}
