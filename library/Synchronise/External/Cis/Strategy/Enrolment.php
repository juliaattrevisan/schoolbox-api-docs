<?php
/**
 * Synchronise_Schoolbox_Cis_Strategy_Enrolment
 *
 * Populates the class_period (teachers) and class_user (students) tables.
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

class Synchronise_External_Cis_Strategy_Enrolment extends Synchronise_Base_Strategy_Class
{
    protected $location = '';
    protected $columns  = array();
    protected $title    = "Update Schoolbox Enrolment Details Synchronisation";

    /**
     * {@inheritdoc}
     */
    public function generateList()
    {
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
        $classMapper = ClassEntryMapper::getInstance();
        $className = $new_item['ClassName'];
        foreach ($new_item['Set'] as $set) {
            $classCode   = $set['ClassCode'];
            $classId     = $set['ID'];
            if (empty($classCode)) {
                printf("Skipping class without code \"%s\"\n", $className);
            }

            // Create or update class
            // I am setting the ID of the class directly, instead of auto-increment
            /** @var ClassEntry $class */
            try {
                $class = $classMapper->findById($classId);
                if ($this->perform_update) {
                    printf("Updating class \"%s\"\n", $classCode);
                } else {
                    printf("Planning update of class \"%s\"\n", $classCode);
                }
            } catch (Exception $e) {
                $class = $classMapper->createModel($classId);
                if ($this->perform_update) {
                    printf("Creating class \"%s\"\n", $classCode);
                } else {
                    printf("Planning create of class \"%s\"\n", $classCode);
                }
            }
            $class
                ->setCode($classCode)
                ->setName($className)
            ;

            if ($this->perform_update) {
                $classMapper->save($class);
                $this->createUpdateClassPeriods($new_item, $class);
            }
        }
        return 1;
    }

    /**
     * Creates or updates periods for a class.
     *
     * @param array      $newItem Array passed to `executeCreateUpdate()`.
     * @param ClassEntry $class   The class to add periods to.
     *
     * @throws \Schoolbox\Mapper\Exception
     */
    public function createUpdateClassPeriods($newItem, ClassEntry $class)
    {
        $periodMapper = ClassPeriodMapper::getInstance();

        // Clear the existing teachers for this class
        $periodMapper->deleteByColumn('class_id', $class->getId());

        // Re-add teachers in this class
        $periods = new \Schoolbox\Collection();
        /** @var User[] $teachers */
        $teachers = UserMapper::getInstance()->findByColumn(
            'synergy_id',
            array_map(
                function ($v) {
                    return 'T-'.$v;
                },
                $newItem['ClassTeacherIds']
            )
        );
        foreach ($teachers as $teacher) {
            foreach ($newItem['ClassPeriods'] as $classPeriod) {
                /** @var ClassPeriod $period */
                $period = $periodMapper->createModel();
                $periods->add(
                    $period
                        ->setClass($class)
                        ->setTeacher($teacher)
                        ->setPeriod($classPeriod['ClassPeriodID'])
                );
            }
        }
        $periodMapper->save($periods);
    }
}
