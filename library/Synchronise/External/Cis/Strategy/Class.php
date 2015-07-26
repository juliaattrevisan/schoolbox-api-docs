<?php
/**
 * Synchronise_Schoolbox_Cis_Strategy_Class
 *
 * Populates the class and class_period (period and teacher info) tables.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassPeriod;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Synchronise_External_Cis_Strategy_Class extends Synchronise_Base_Strategy_Class
{
    protected $location = '';
    protected $columns  = array();
    protected $title    = "Update Schoolbox Class Details Synchronisation";

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
        $class = $this->createUpdateClass($new_item);
//        if ($this->perform_update) {
//            $this->createUpdateClassPeriods($new_item, $class);
//        }
        return 1;
    }

    /**
     * Creates or updates a new class entry.
     *
     * @param array $newItem Array passed to `executeCreateUpdate()`.
     *
     * @return ClassEntry
     */
    public function createUpdateClass($newItem)
    {
        $classCode   = $newItem['ClassCode'];
        $classMapper = ClassEntryMapper::getInstance();

        // Create or update class
        /** @var ClassEntry $class */
        try {
            $class = $classMapper->findOneByColumn('code', $classCode);
            if ($this->perform_update) {
                printf("Updating class \"%s\"\n", $classCode);
            } else {
                printf("Planning update of class \"%s\"\n", $classCode);
            }
        } catch (Exception $e) {
            $class = $classMapper->createModel();
            $class->setCode($classCode);
            if ($this->perform_update) {
                printf("Creating class \"%s\"\n", $classCode);
            } else {
                printf("Planning create of class \"%s\"\n", $classCode);
            }
        }
        $class->setName($newItem['ClassName']);
        $classMapper->save($class);
        return $class;
    }

//    /**
//     * Creates or updates periods for a class.
//     *
//     * @param array      $newItem Array passed to `executeCreateUpdate()`.
//     * @param ClassEntry $class   The class to add periods to.
//     *
//     * @throws \Schoolbox\Mapper\Exception
//     */
//    public function createUpdateClassPeriods($newItem, ClassEntry $class)
//    {
//         // Update teachers in this class too
//        $periodMapper = ClassPeriodMapper::getInstance();
//        /** @var User[] $teachers */
//        $teachers     = UserMapper::getInstance()->findByColumn(
//            'synergy_id',
//            $newItem['ClassTeacherIds']
//        );
//        foreach ($teachers as $teacher) {
//            $search = [
//                'class_id'   => $class->getId(),
//                'teacher_id' => $teacher->getId(),
//            ];
//            $periodMapper->deleteByColumnList($search);
//        }
//        // Fill periods in again for this teacher
//    }
}
