<?php
/**
 * Synchronise_Schoolbox_Cis_Strategy_Enrolment
 *
 * Populates the class, class_period (teachers) and class_user (students) tables.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvement;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriod;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Synchronise_External_Cis_Strategy_Enrolment extends Synchronise_Base_Strategy_Class
{
    use Synchronise_External_Cis_Strategy_BatchXmlTrait;

    protected $location = '';
    protected $columns  = array();
    protected $title    = "Update Schoolbox Enrolment Details Synchronisation";

    /**
     * {@inheritdoc}
     */
    public function generateList()
    {
        try {
            $iterator   = new External_Isams_Batch_Reader_Enrolment($this->getXmlReader());
            $this->list = $iterator->getAccountInfo();
        } catch (Exception $e) {
            printf('Skipping enrolment sync: '.$e->getMessage());
        }
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
        $className   = $new_item['ClassName'];
        $classCode   = $new_item['ClassCode'];
        if (empty($classCode)) {
            printf("Skipping class without code \"%s\"\n", $className);
            return 1;
        }

        // Create or update class
        // I am setting the ID of the class directly, instead of auto-increment
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
            $this->createUpdateClassEnrolments($new_item, $class);
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
                $newItem['ClassTeachers']
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
                        ->setRoom($classPeriod['ClassRoomCode'])
                );
            }
        }
        $periodMapper->save($periods);
    }

    /**
     * Creates or updates students for a class.
     *
     * @param array      $newItem Array passed to `executeCreateUpdate()`.
     * @param ClassEntry $class   The class to add students to.
     *
     * @throws \Schoolbox\Mapper\Exception
     */
    public function createUpdateClassEnrolments($newItem, ClassEntry $class)
    {
        $enrolmentMapper = ClassInvolvementMapper::getInstance();

        // Clear the existing enrolments for this class
        $enrolmentMapper->deleteByColumn('class_id', $class->getId());

        // Re-add enrolments in this class
        $enrolments = new \Schoolbox\Collection();
        /** @var User[] $teachers */
        $students = UserMapper::getInstance()->findByColumn(
            'synergy_id',
            array_map(
                function ($v) {
                    return 'S-'.$v;
                },
                $newItem['ClassStudents']
            )
        );
        foreach ($students as $student) {
            /** @var ClassInvolvement $enrolment */
            $enrolment = $enrolmentMapper->createModel();
            $enrolments->add(
                $enrolment
                    ->setClass($class)
                    ->setUser($student)
            );
        }
        $enrolmentMapper->save($enrolments);

    }
}
