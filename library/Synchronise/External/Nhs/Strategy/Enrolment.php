<?php
/**
 * Synchronise_Schoolbox_Nhs_Strategy_Enrolment
 *
 * Uploads student class entrolments.
 *
 * Currently the class export would use only following columns:
 * * Student: maps to user.external_id
 * * Class: maps to class.code
 * The subject and teacher fields would not matter.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\UserMapper;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvement;
use Schoolbox\External\ClassInvolvementMapper;

class Synchronise_External_Nhs_Strategy_Enrolment extends Synchronise_Base_Strategy_Enrolment {

    protected $location = '';
    protected $columns  = array();
    protected $title    = "Update Schoolbox Enrolment Details Synchronisation";

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
    }

    /**
     * {@inheritdoc}
     */
    public function generateList() {
        $this->list = array();
        $reader = new Alaress\Csv\Reader($this->location);
        $reader->setLoadFromFirst(true);
        foreach ($reader as $row) {
            // Study classes (free preiods?) exist, these have a space afterward.
            // These aren't separate classes
            list($code) = explode(' ', $row['Class']);
            $this->list[$code]['code']           = $code;
            $this->list[$code]['student_list'][] = $row['Student'];
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
        // Consider not setting up study classes.
//        if ($new_item['code'] === '12Study.R2') {
//            return 0;
//        }
        $class         = null;
        $class_message = sprintf('enrolment for class "%s"', $new_item['code']);
        // Load existing class
        try {
            $class = ClassEntryMapper::getInstance()->findOneByColumn('code', $new_item['code']);
        }
        catch (Exception $e) {}
        if (!($class instanceof ClassEntry)) {
            $this->verbose_filter->outputSkipMessage($class_message.', because it was not found');
            $this->verbose_filter->incrementSkipped();
            return 0;
        }

        // Student connections:
        // Delete all old ones for this class, then put in the new ones.
        $enrolment_message = count($new_item['student_list']).' enrolment(s) for '.$class_message;
        if ($this->perform_update) {
            ClassInvolvementMapper::getInstance()->deleteInvolvementsForClassAndGroupType($class, Group::STUDENT_GROUP_TYPE);
            $student_list = UserMapper::getInstance()->findIn('synergy_id', $new_item['student_list']);
            foreach ($student_list as $student) {
                $class_involvement = new ClassInvolvement();
                $class_involvement->setClass($class)->setUser($student);
                ClassInvolvementMapper::getInstance()->save($class_involvement);
            }
            $this->verbose_filter->outputPerformUpdateMessage($enrolment_message);
        }
        else {
            $this->verbose_filter->outputPlanUpdateMessage($enrolment_message);
        }
        $this->verbose_filter->incrementUpdated();
        return 1;
    }

}
