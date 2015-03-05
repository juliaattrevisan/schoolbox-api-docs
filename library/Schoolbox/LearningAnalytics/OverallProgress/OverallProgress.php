<?php
/**
 * OverallProgress
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\OverallProgress;

use Schoolbox\Resource\Category as Folder;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Collection;

class OverallProgress {
    /**
     * Folder instance
     *
     * @var Folder
     */
    private $folder = null;

    /**
     * Unit instance
     *
     * @var UnitInstance
     */
    private $unit = null;

    /**
     * Number of students that can appear in the
     * Top Students and Bottom Students lists
     *
     * @var int
     */
    private $maxStudentThreshold = 3;

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
     * This collection has completed the most tasks
     *
     * @var Collection
     */
    private $topStudents = null;

    /**
     * This collection has completed the least tasks
     *
     * @var Collection
     */
    private $bottomStudents = null;

    /**
     * Constructor
     *
     * @param Folder $folder
     * @param UnitInstance $unit
     */
    public function __construct(Folder $folder, UnitInstance $unit) {
        $this->setFolder($folder);
        $this->setUnit($unit);

        $this->process();
    }

    /**
     * Populate with data
     */
    private function process() {
        $studentListProcessor = new StudentList($this->getFolder(), $this->getUnit());
        $allStudents = $studentListProcessor->getStudentList();

        $taskCompletions = new TaskCompletions($allStudents, $this->getUnit());

        /* A list of the top N students
         *
         * These are people who have completed the most tasks,
         * sorted by count in descending order
         */
        $topStudents = new Collection();

        $allTaskCompletions = $taskCompletions->findDescending();

        for ($i = 0; $i < $this->maxStudentThreshold; $i++) {
            if (isset($allTaskCompletions[$i])) {
                $currentTaskCompletion = $allTaskCompletions[$i];

                /* Top students should have at least one completed
                 * item in order to be listed here
                 */
                if (count($currentTaskCompletion['tasks'])) {
                    $topStudents->add($currentTaskCompletion);
                }
            }
        }

        $this->setTopStudents($topStudents);

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

        /* A list of the bottom N students
         *
         * These are people who have completed the most tasks,
         * sorted by count in ascending order
         */
        $bottomStudents = new Collection();

        $allTaskCompletions = $taskCompletions->findAscending();

        for ($i = 0; $i < $this->maxStudentThreshold; $i++) {
            if (isset($allTaskCompletions[$i])) {
                $currentTaskCompletion = $allTaskCompletions[$i];

                $bottomStudents->add($currentTaskCompletion);
            }
        }

        $this->setBottomStudents($bottomStudents);

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
            if ($value == $allStudents->count()) {
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
     * Set folder
     *
     * @param Folder $folder
     */
    private function setFolder($folder)
    {
        $this->folder = $folder;
    }

    /**
     * Get folder
     *
     * @return Folder
     */
    private function getFolder()
    {
        return $this->folder;
    }

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
     * Set bottom students
     *
     * @param \Schoolbox\Collection $bottomStudents
     */
    private function setBottomStudents($bottomStudents)
    {
        $this->bottomStudents = $bottomStudents;
    }

    /**
     * Get bottom students
     *
     * @return \Schoolbox\Collection
     */
    public function getBottomStudents()
    {
        return $this->bottomStudents;
    }

    /**
     * Set top students
     *
     * @param \Schoolbox\Collection $topStudents
     */
    private function setTopStudents($topStudents)
    {
        $this->topStudents = $topStudents;
    }

    /**
     * Get top students
     *
     * @return \Schoolbox\Collection
     */
    public function getTopStudents()
    {
        return $this->topStudents;
    }
}