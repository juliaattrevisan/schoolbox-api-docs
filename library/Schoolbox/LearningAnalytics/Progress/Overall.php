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
use Schoolbox\LearningAnalytics\Registry;

class Overall {

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
     * An instance of LearningAnalytics\Registry
     *
     * @var Registry
     */
    private $registry = null;

    /**
     * Constructor
     *
     * @param Registry $registry
     */
    public function __construct(Registry $registry) {
        $this->setRegistry($registry);

        $this->process();
    }

    /**
     * Populate with data
     */
    private function process() {
        $this->setMostAdvancedStudent($this->findMostAdvancedStudent());
        $this->setClassCompletions($this->findClassCompletions());
    }

    /**
     * Find the student who has completed the most tasks
     * Remember how many they completed
     *
     * @return float
     */
    private function findMostAdvancedStudent() {
        $allCompletions = array();
        $numTasks = 0;
        $val = 0.00;

        $allStudentRegistry = $this->getRegistry()->getAllStudentRegistry();
        $allUnitRegistry = $this->getRegistry()->getAllUnitRegistry();

        /* Tally completed tasks across all units in a course
         */
        foreach ($allUnitRegistry as $currentUnit) {
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
        }

        return $val;
    }

    /**
     * Find out the overall progress throughout all tasks
     * in all units
     *
     * This is the number of tasks that all students have
     * marked as completed, represented as a percentage
     * of all the tasks that are available
     *
     * If nobody has completed a task, it won't appear in
     * the $completionCounts array
     */
    private function findClassCompletions() {
        $completionCounts = array();
        $numTasks = 0;

        $allStudentRegistry = $this->getRegistry()->getAllStudentRegistry();

        foreach ($this->getRegistry()->getAllUnitRegistry() as $currentUnit) {
            $numTasks += $currentUnit->getTasks()->count();

            $allStudents = $allStudentRegistry[$currentUnit->getId()];

            $taskCompletions = new TaskCompletions($allStudents, $currentUnit);
            $allTaskCompletions = $taskCompletions->findAlphabetical();

            foreach ($allTaskCompletions as $currentTaskCompletion) {
                if (count($currentTaskCompletion['tasks'])) {
                    foreach ($currentTaskCompletion['tasks'] as $currentTask) {
                        $completionCounts[$currentTask]['unitId'] = $currentUnit->getId();
                        $completionCounts[$currentTask]['count']++;
                    }
                }
            }
        }

        /* Find out which tasks have been completed by all students
         */
        $classCompletions = 0;

        foreach ($completionCounts as $tuple) {
            if ($tuple['count'] == $allStudentRegistry[$tuple['unitId']]->count()) {
                $classCompletions++;
            }
        }

        if ($numTasks) {
            return 100 * $classCompletions / $numTasks;
        }

        return 0;
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
     * Set registry
     *
     * @param \Schoolbox\LearningAnalytics\Registry $registry
     */
    private function setRegistry($registry)
    {
        $this->registry = $registry;
    }

    /**
     * Get registry
     *
     * @return \Schoolbox\LearningAnalytics\Registry
     */
    private function getRegistry()
    {
        return $this->registry;
    }
}