<?php
/**
 * TaskCompletions
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics;

use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Collection;

class TaskCompletions {

    /**
     * A collection of User instances
     *
     * @var null
     */
    private $allStudents = null;

    /**
     * Unit instance
     *
     * @var UnitInstance
     */
    private $unit = null;

    /**
     * A loose struct of User instance and a list of ids
     * of tasks that have been completed
     *
     * @var array
     */
    private $completions = array();

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
     * Process everything
     */
    private function process() {
        $completions = array();

        foreach ($this->getAllStudents() as $currentStudent) {
            $completions[$currentStudent->getId()] = array(
                'user' => $currentStudent,
                'tasks' => array()
            );

            foreach ($this->getUnit()->getTasks() as $currentTask) {
                foreach ($currentTask->getCompletionList() as $currentCompletion) {
                    if ($currentCompletion->getStudent()->getId() == $currentStudent->getId()) {
                        $completions[$currentStudent->getId()]['tasks'][] = $currentTask->getId();
                    }
                }
            }

            $completions[$currentStudent->getId()]['tasks'] = array_unique($completions[$currentStudent->getId()]['tasks']);
        }

        $this->setCompletions(array_values($completions));
    }

    /**
     * Use default sort order (surname)
     *
     * @return array
     */
    public function findAlphabetical() {
        return $this->getCompletions();
    }

    /**
     * Sort completions based on number of items completed
     * and return new order
     */
    public function findAscending() {
        $temp = $this->getCompletions();

        uasort($temp, function($a, $b) {
            return count($a['tasks']) - count($b['tasks']);
        });

        return array_values($temp);
    }

    /**
     * Sort completions based on number of items completed
     * and return new order
     */
    public function findDescending() {
        $temp = $this->getCompletions();

        uasort($temp, function($a, $b) {
            return count($b['tasks']) - count($a['tasks']);
        });

        return array_values($temp);
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
     * Set all students
     *
     * @param null $allStudents
     */
    private function setAllStudents($allStudents)
    {
        $this->allStudents = $allStudents;
    }

    /**
     * Get all students
     *
     * @return null
     */
    private function getAllStudents()
    {
        return $this->allStudents;
    }

    /**
     * Set completions
     *
     * @param array $completions
     */
    private function setCompletions($completions)
    {
        $this->completions = $completions;
    }

    /**
     * Get completions
     *
     * @return array
     */
    private function getCompletions()
    {
        return $this->completions;
    }
}