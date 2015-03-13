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
use Schoolbox\LearningAnalytics\Sorter;

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

        $this->process($this->preloadUnits());
    }

    /**
     * Populate with data
     */
    private function process($preloadedUnits) {
        $this->setMostAdvancedStudent($this->findMostAdvancedStudent($preloadedUnits));
        $this->setClassCompletions($this->findClassCompletions($preloadedUnits));
    }

    /**
     * Preload shared data between methods
     *
     * @return array
     */
    private function preloadUnits() {
        $preload = array();

        $everyone = $this->getRegistry()->getAllStudentRegistry();

        foreach ($this->getRegistry()->getAllUnitRegistry() as $currentUnit) {
            $allStudents = $everyone[$currentUnit->getId()];

            $unitProgress = new Unit($allStudents, $currentUnit);

            $preload[$currentUnit->getId()] = $unitProgress;
        }

        return $preload;
    }

    /**
     * Find the student who has completed the most tasks
     * Remember how much they completed as a percentage
     *
     * @param $preloadedUnits
     * @return float
     */
    private function findMostAdvancedStudent($preloadedUnits) {
        $mostAdvancedStudent = 0;

        /* Remember the most that was completed
         */
        foreach ($this->getRegistry()->getAllUnitRegistry() as $currentUnit) {
            $val = $preloadedUnits[$currentUnit->getId()]->getMostAdvancedStudent();

            $mostAdvancedStudent = max($mostAdvancedStudent, $val);
        }

        return $mostAdvancedStudent;
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
     *
     * @param $preloadedUnits
     * @return float|int
     */
    private function findClassCompletions($preloadedUnits) {
        $classCompletions = 0;

        /* Average the two values
         */
        foreach ($this->getRegistry()->getAllUnitRegistry() as $currentUnit) {
            $val = $preloadedUnits[$currentUnit->getId()]->getClassCompletions();

            $classCompletions = 0.5 * ($classCompletions + $val);
        }

        return $classCompletions;
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