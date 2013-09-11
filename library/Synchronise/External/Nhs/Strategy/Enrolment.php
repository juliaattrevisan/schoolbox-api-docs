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

    public $columns = array();

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
        // Where's the CSV file? Let's say in students_csv for now.
        // Need a better method for loading students too.
        $fp = fopen(__DIR__.'/enrolments.csv', 'r');
        $this->columns = fgetcsv($fp, null, ',', '"');
        while (($row = fgetcsv($fp, null, ',', '"')) !== false) {
            $list_row = array();
            foreach ($this->columns as $i => $column) {
                if (isset($column)) {
                    $list_row[$column] = isset($row[$i]) ? $row[$i] : null;
                }
            }
            // Study classes (free preiods?) exist, these have a space afterward.
            // These aren't separate classes
            list($code) = explode(' ', $list_row['Class']);
            $this->list[$code]['code']           = $code;
            $this->list[$code]['student_list'][] = $list_row['Student'];
        }

        // Need to group students and their classes together

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
        // Consider not setting up study classes.
//        if ($new_item['code'] === '12Study.R2') {
//            return 0;
//        }

        // Load existing class
        try {
            $class = ClassEntryMapper::getInstance()->findOneByColumn('code', $new_item['code']);
        }
        catch (Exception $e) {
            return 0;
        }
        if (!($class instanceof ClassEntry)) {
            return 0;
        }

        // Student connections:
        // Delete all old ones for this class, then put in the new ones.

        // Message: setting %d students into class %s
        ClassInvolvementMapper::getInstance()->deleteInvolvementsForClassAndGroupType($class, Group::STUDENT_GROUP_TYPE);
        $student_list = UserMapper::getInstance()->findIn('synergy_id', $new_item['student_list']);
        printf(
            "Setting %d students as enrolled in class \"%s\"\n",
            count($student_list),
            $new_item['code']
        );
        foreach ($student_list as $student) {
            $class_involvement = new ClassInvolvement();
            $class_involvement->setClass($class)->setUser($student);
            ClassInvolvementMapper::getInstance()->save($class_involvement);
        }
        return 1;
    }

}
