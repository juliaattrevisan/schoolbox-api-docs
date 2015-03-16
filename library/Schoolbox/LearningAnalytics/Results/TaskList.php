<?php
/**
 * TaskList
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\Results;

use Schoolbox\LearningAnalytics\Registry;

class TaskList {

    /**
     * An instance of LearningAnalytics\Registry
     *
     * @var Registry
     */
    private $registry = null;

    /**
     * An array of taskId-task relations
     *
     * Note this stacks them in the source task's id, not the
     * individual derivative instances of that task
     *
     * @var array
     */
    private $allTasks = array();

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
     * Task List Union
     * All the tasks in all the units
     *
     * Group by the originating task id, not the task instance id
     * This is so you can stack results from multiple classes
     */
    private function process() {

        /* A unique list of all tasks
         */
        $allTasks = array();

        foreach ($this->getRegistry()->getAllUnitRegistry() as $currentUnit) {
            foreach ($currentUnit->getTasks() as $currentTask) {
                $allTasks[$currentTask->getTask()->getId()] = $currentTask;
            }
        }

        $this->setAllTasks(array_values($allTasks));
    }

    /* Getters/Setters
     */

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

    /**
     * Set all tasks
     *
     * @param array $allTasks
     */
    private function setAllTasks($allTasks)
    {
        $this->allTasks = $allTasks;
    }

    /**
     * Get all tasks
     *
     * @return array
     */
    public function getAllTasks()
    {
        return $this->allTasks;
    }
}