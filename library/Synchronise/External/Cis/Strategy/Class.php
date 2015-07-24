<?php
/**
 * Synchronise_Schoolbox_Cis_Strategy_Class
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

use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassPeriod;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Synchronise_External_Cis_Strategy_Class extends Synchronise_Base_Strategy_Class {

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
        $reader = new XMLReader();
        $reader->open(SITE_root.'/classes.xml');
        $iterator   = new External_Isams_Batch_Reader_Classes($reader);
        $this->list = $iterator->getAccountInfo();
    }

    /**
     * Setup verbose output
     */
    protected function setup()
    {
        $this->verbose_filter = new Synchronise_External_Cis_Verbose();
    }

    /**
     * Converts the synced item into a format that executeCreateUpdate() can
     * understand.
     *
     * @param  array $list_item
     * @return array
     */
    public function setupSyncedItemDetails($list_item)
    {
        return $list_item;
    }

    /**
     * Runs the create/update script for this class.
     *
     * @param  array $new_item
     * @return int
     */
    public function executeCreateUpdate($new_item)
    {
        // Create or update class
        $class   = null;
        $message = sprintf("%s class \"%s\" (%s)\n", '%s', $class['ClassName'], $class['ClassCode']);
        try {
            $class = ClassEntryMapper::getInstance()->findOneByColumn('code', $new_item['ClassCode']);
        }
        catch (Exception $e) {
            // noop
        }
        if ($class instanceof ClassEntry) {
            if ($this->perform_update) {
                printf($message, 'Updating ');
                $this->verbose_filter->performUpdate();
            }
            else {
                printf($message, 'Planning update of ');
                $this->verbose_filter->planUpdate();
            }
            $this->verbose_filter->incrementUpdated();
        }
        else {
            $class = new ClassEntry();
            $class->setCode($new_item['ClassCode']);
            if ($this->perform_update) {
                printf($message, 'Creating ');
                $this->verbose_filter->performCreation();
            }
            else {
                printf($message, 'Planning create of ');
                $this->verbose_filter->planCreation();
            }
            $this->verbose_filter->incrementCreated();
        }
        $class->setName($new_item['ClassName']);
        if ($this->perform_update) {
            ClassEntryMapper::getInstance()->save($class);

            // Save teachers in period too
        }

        // Update teachers in this class too
        $periodMapper = ClassPeriodMapper::getInstance();
        /** @var User[] $teachers */
        $teachers     = UserMapper::getInstance()->findByColumn('synergy_id', $new_item['ClassTeacherIds']);
        foreach ($teachers as $teacher) {
            $periodMapper->deleteByColumnList([
                'class_id'   => $class->getId(),
                'teacher_id' => $teacher->getId(),
            ]);
            try {
                $period = $periodMapper->findOneByColumnList(
                    'code',
                    $new_item['ClassCode']
                );
            }
            catch (Exception $e) {
                // noop
            }
        }

        return 1;
    }
}
