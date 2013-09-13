<?php
/**
 * Synchronise_Schoolbox_Nhs_Strategy_Class
 *
 * Uploads classes - most fields from the upload are ignored for now, our system
 * doesn't store all the fields that class and student management systems do.
 *
 * Currently the class export would use only following columns:
 * * Code:    maps to class.code
 * * Teacher: maps to a code in users table, and creates entry in class_user
 * * Room:    comes from timetable instead
 * * Subject: maps to class.name
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

class Synchronise_External_Nhs_Strategy_Class extends Synchronise_Base_Strategy_Class {

    protected $location = '';
    protected $columns  = array();
    protected $title    = "Update Schoolbox Class Details Synchronisation";

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
     * {@inheritdoc}
     */
    public function generateList() {
        $this->list = array();
        $reader = new Alaress\Csv\Reader($this->location);
        $reader->setLoadFromFirst(true);
        foreach ($reader as $row) {
            $this->list[] = $row;
        }
    }

    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_External_Nhs_Verbose();
    }

    /**
     * Converts the synced item into a format that executeCreateUpdate() can
     * understand.
     *
     * @param  array $list_item
     * @return array
     */
    public function setupSyncedItemDetails($list_item) {
        $class = array(
            'name'         => $list_item['Subject'],
            'code'         => $list_item['Code'],
            'teacher_list' => explode(';', $list_item['Teacher']),
        );
        // Enrolment gets figured out as part of separate export.
        return $class;
    }

    /**
     * Runs the create/update script for this class.
     *
     * @param  array $new_item
     * @return int
     */
    public function executeCreateUpdate($new_item) {
        // I'm going to assume that code is unique.
        // Did you know that the Donvale class sync deletes and recreates all
        // classes?
        try {
            $class = ClassEntryMapper::getInstance()->findOneByColumn('code', $new_item['code']);
        }
        catch (Exception $e) {}
        if (empty($class)) {
            $class = new ClassEntry();
            $class->setCode($new_item['code']);
        }
        $class->setName($new_item['name']);
        ClassEntryMapper::getInstance()->save($class);

        // Teacher connections:
        // Delete all old ones for this class, then put in the new ones.
        ClassInvolvementMapper::getInstance()->deleteInvolvementsForClassAndGroupType($class, Group::STAFF_GROUP_TYPE);
        $teacher_list = UserMapper::getInstance()->findIn('synergy_id', $new_item['teacher_list']);printf(
            "Adding %d teachers for class \"%s (%s)\"\n",
            count($teacher_list),
            $new_item['name'],
            $new_item['code']
        );
        foreach ($teacher_list as $teacher) {
            $class_involvement = new ClassInvolvement();
            $class_involvement->setClass($class)->setUser($teacher);
            ClassInvolvementMapper::getInstance()->save($class_involvement);
        }
        return 1;
    }
}
