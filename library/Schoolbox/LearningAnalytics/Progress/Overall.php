<?php
/**
 * Overall
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\Progress;

use Schoolbox\Collection;
use Schoolbox\Resource\Category as Folder;
use Schoolbox\LearningAnalytics\StudentList;

class Overall {

    /**
     * A folder
     *
     * @var Folder
     */
    private $folder = null;

    /**
     * A collection of UnitInstances
     *
     * @var Collection
     */
    private $allUnits = null;

    /**
     * This student has completed the most tasks
     *
     * @var float
     */
    private $mostAdvancedStudent = 0;

    /**
     * All students have completed this many tasks
     *
     * @var int
     */
    private $classCompletions = 0;

    /**
     * A collection of User instances
     *
     * @var Collection
     */
    private $allStudents = null;

    /**
     * Constructor
     *
     * @param Folder $folder
     * @param Collection $allUnits
     */
    public function __construct(Folder $folder, Collection $allUnits) {
        $this->setFolder($folder);
        $this->setAllUnits($allUnits);

        $this->process();
    }

    /**
     * A list of all the students who are associated with each unit
     *
     * This basically serves as a preload for the external db, because
     * we reuse this list of students and we don't want to hit the
     * external db more than we have to
     *
     * @return array
     */
    private function findStudentRegistry() {
        $allStudentRegistry = array();

        /* Extract all the students first because we're going to
         * reuse this list again
         */
        foreach ($this->getAllUnits() as $currentUnit) {
            $studentListProcessor = new StudentList($this->getFolder(), $currentUnit);
            $allStudentRegistry[$currentUnit->getId()] = $studentListProcessor->getStudentList();
        }

        return $allStudentRegistry;
    }

    /**
     * Find the student who has completed the most tasks
     * Remember how many they completed
     *
     * @param $allStudentRegistry
     * @return float
     */
    private function findMostAdvancedStudent($allStudentRegistry) {
        $allCompletions = array();
        $numTasks = 0;
        $val = 0.00;

        /* Tally completed tasks across all units in a course
         */
        foreach ($this->getAllUnits() as $currentUnit) {
            $numTasks += $currentUnit->getTasks()->count();

            $allStudents = $allStudentRegistry[$currentUnit->getId()];

            $taskCompletions = new TaskCompletions($allStudents, $currentUnit);

            $allTaskCompletions = $taskCompletions->findAlphabetical();

            /* Top students should have at least one completed
             * item in order to be listed
             */
            foreach ($allTaskCompletions as $currentTaskCompletion) {
                if (count($currentTaskCompletion['tasks'])) {

                    /* UserId-TaskId relation
                     * Declare a stack if it isn't one already
                     */
                    if (!is_array($allCompletions[$currentTaskCompletion['user']->getId()])) {
                        $allCompletions[$currentTaskCompletion['user']->getId()] = array();
                    }

                    /* Combine completed tasks, deduping
                     */
                    $allCompletions[$currentTaskCompletion['user']->getId()] = array_filter(array_unique(array_merge(
                        $allCompletions[$currentTaskCompletion['user']->getId()],
                        $currentTaskCompletion['tasks']
                    )));
                }
            }
        }

        /* Do a tally of unique completions
         */
        $uniqueCompletionCounts = array();

        foreach ($allCompletions as $userId => $taskIds) {
            $uniqueCompletionCounts[$userId] = count($taskIds);
        }

        /* Sort in descending order
         */
        if (count($uniqueCompletionCounts)) {
            uasort($uniqueCompletionCounts, function($a, $b) {
                return $b - $a;
            });

            /* The highest number of tasks completed by a student
             */
            if ($numTasks) {
                $val = 100 * array_shift($uniqueCompletionCounts) / $numTasks;
            }
            else {
                $val = 0;
            }
        }

        return $val;
    }

    /**
     * Populate with data
     */
    private function process() {
        $allStudentRegistry = $this->findStudentRegistry();

        $this->setMostAdvancedStudent($this->findMostAdvancedStudent($allStudentRegistry));

//
//            /* Find out the overall progress throughout all tasks
//             * This is the number of tasks that all students have
//             * marked as completed, represented as a percentage
//             * of all the tasks that are available
//             */
//            $completionCounts = array();
//
//            foreach ($allTaskCompletions as $currentTaskCompletion) {
//                foreach ($currentTaskCompletion['tasks'] as $currentTask) {
//                    $completionCounts[$currentTask]++;
//                }
//            }
//
//            $classCompletions = 0;
//
//            foreach ($completionCounts as $key => $value) {
//                if ($value == $this->getAllStudents()->count()) {
//                    $classCompletions++;
//                }
//            }
//
//            if ($this->getUnit()->getTasks()->count()) {
//                $classCompletions = 100 * $classCompletions/$this->getUnit()->getTasks()->count();
//            }
//            else {
//                $classCompletions = 0;
//            }
//
//            $this->setClassCompletions($classCompletions);

    }

    /* Getters/Setters
     */

    /**
     * Set class completions
     *
     * @param int $classCompletions
     */
    private function setClassCompletions($classCompletions)
    {
        $this->classCompletions = $classCompletions;
    }

    /**
     * Get class completions
     *
     * @return int
     */
    public function getClassCompletions()
    {
        return $this->classCompletions;
    }

    /**
     * Set most advanced student
     *
     * @param float $mostAdvancedStudent
     */
    private function setMostAdvancedStudent($mostAdvancedStudent)
    {
        $this->mostAdvancedStudent = $mostAdvancedStudent;
    }

    /**
     * Get most advanced student
     *
     * @return float
     */
    public function getMostAdvancedStudent()
    {
        return $this->mostAdvancedStudent;
    }

    /**
     * Set all students
     *
     * @param \Schoolbox\Collection $allStudents
     */
    private function setAllStudents($allStudents)
    {
        $this->allStudents = $allStudents;
    }

    /**
     * Get all students
     *
     * @return \Schoolbox\Collection
     */
    private function getAllStudents()
    {
        return $this->allStudents;
    }

    /**
     * Set folder
     *
     * @param \Schoolbox\Resource\Category $folder
     */
    private function setFolder($folder)
    {
        $this->folder = $folder;
    }

    /**
     * Get folder
     *
     * @return \Schoolbox\Resource\Category
     */
    private function getFolder()
    {
        return $this->folder;
    }

    /**
     * Set all units
     *
     * @param \Schoolbox\Collection $allUnits
     */
    private function setAllUnits($allUnits)
    {
        $this->allUnits = $allUnits;
    }

    /**
     * Get all units
     *
     * @return \Schoolbox\Collection
     */
    private function getAllUnits()
    {
        return $this->allUnits;
    }
}