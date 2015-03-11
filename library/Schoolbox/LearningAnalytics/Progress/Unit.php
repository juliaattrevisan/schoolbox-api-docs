<?php
/**
 * OverallProgress
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\Progress;

use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Collection;

class Unit {

    /**
     * Unit instance
     *
     * @var UnitInstance
     */
    private $unit = null;

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
     * @param Collection $allStudents
     * @param UnitInstance $unit
     */
    public function __construct(Collection $allStudents, UnitInstance $unit) {
        $this->setAllStudents($allStudents);
        $this->setUnit($unit);

        $this->process();
    }

    /**
     * Populate with data
     */
    private function process() {
        $taskCompletions = new TaskCompletions($this->getAllStudents(), $this->getUnit());

        /* A list of the top N students
         *
         * These are people who have completed the most tasks,
         * sorted by count in descending order
         */
        $topStudents = new Collection();

        $allTaskCompletions = $taskCompletions->findDescending();

        /* Top students should have at least one completed
         * item in order to be listed
         */
        foreach ($allTaskCompletions as $currentTaskCompletion) {
            if (count($currentTaskCompletion['tasks'])) {
                $topStudents->add($currentTaskCompletion);
            }
        }

        /* This, coincidentally, helps you determine the racehorses
         * of the class which are represented by the orange bar
         *
         * That bar tells you how many tasks a student has completed
         * as a percentage of all the available tasks (horizontal
         * ticks on the progressReport screen)
         *
         * Expressed as a percentage
         */
        $mostAdvancedStudent = 0;

        if ($topStudents->count() && $this->getUnit()->getTasks()->count()) {
            $topStudent = $topStudents->first();
            $mostAdvancedStudent = 100 * count($topStudent['tasks'])/$this->getUnit()->getTasks()->count();
        }

        $this->setMostAdvancedStudent($mostAdvancedStudent);

        /* Find out the overall progress throughout all tasks
         * This is the number of tasks that all students have
         * marked as completed, represented as a percentage
         * of all the tasks that are available
         */
        $completionCounts = array();

        foreach ($allTaskCompletions as $currentTaskCompletion) {
            foreach ($currentTaskCompletion['tasks'] as $currentTask) {
                $completionCounts[$currentTask]++;
            }
        }

        $classCompletions = 0;

        foreach ($completionCounts as $key => $value) {
            if ($value == $this->getAllStudents()->count()) {
                $classCompletions++;
            }
        }

        if ($this->getUnit()->getTasks()->count()) {
            $classCompletions = 100 * $classCompletions/$this->getUnit()->getTasks()->count();
        }
        else {
            $classCompletions = 0;
        }

        $this->setClassCompletions($classCompletions);
    }

    /* Getters/Setters
     */

    /**
     * Set unit
     *
     * @param UnitInstance $unit
     */
    private function setUnit($unit)
    {
        $this->unit = $unit;
    }

    /**
     * Get unit
     *
     * @return UnitInstance
     */
    private function getUnit()
    {
        return $this->unit;
    }

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
}