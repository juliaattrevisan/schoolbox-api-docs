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
use Schoolbox\LearningAnalytics\Metadata;

class TaskList {

    /**
     * An instance of LearningAnalytics\Registry
     *
     * @var Registry
     */
    private $registry = null;

    /**
     * Request metadata
     *
     * @var Metadata
     */
    private $metadata = null;

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
     * @param Metadata $metadata
     */
    public function __construct(Registry $registry, Metadata $metadata) {
        $this->setRegistry($registry);
        $this->setMetadata($metadata);

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

        /* A unique list of all tasks which get matched with data in TaskResults
         *
         * A task that has a mark is one that has either
         * + A submission box instance with a numeric id, or
         * + An assessment instance with a numeric id
         *
         * Tasks always come with objects for submission_box and assessment,
         * but they could be entirely empty. That denotes a task which does not
         * require a mark.
         *
         * Ignore all tasks which have no mark requirement. Ignoring them
         * here prevents matching in TaskResults(), effectively removing
         * them from the boxplots.
         */
        $allTasks = array();

        foreach ($this->getRegistry()->getAllUnitRegistry() as $currentUnit) {
            foreach ($currentUnit->getTasks() as $currentTask) {

                /* If this is a submissionBox
                 */
                if (is_numeric($currentTask->getSubmissionBox()->getId())) {

                    /* If a taskId filter was selected, only include those types
                     * Otherwise include them all
                     */
                    if ($this->getMetadata()->getSelectedTaskIdFilter()) {
                        if ($this->getMetadata()->getSelectedTaskIdFilter() == $currentTask->getSubmissionBox()->getWorkType()->getId()) {
                            $allTasks[$currentTask->getTask()->getId()] = $currentTask;
                        }
                    }
                    else {
                        $allTasks[$currentTask->getTask()->getId()] = $currentTask;
                    }
                }
                /* If this is a Quiz
                 */
                else if (is_numeric($currentTask->getAssessment()->getId())) {

                    /* If a taskId filter was selected, only include those types
                     * Otherwise include them all
                     */
                    if ($this->getMetadata()->getSelectedTaskIdFilter()) {
                        if ($this->getMetadata()->getSelectedTaskIdFilter() == $currentTask->getAsssessment()->getWorkType()->getId()) {
                            $allTasks[$currentTask->getTask()->getId()] = $currentTask;
                        }
                    }
                    else {
                        $allTasks[$currentTask->getTask()->getId()] = $currentTask;
                    }
                }
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

    /**
     * Set metadata
     *
     * @param \Schoolbox\LearningAnalytics\Metadata\Base $metadata
     */
    private function setMetadata($metadata)
    {
        $this->metadata = $metadata;
    }

    /**
     * Get metadata
     *
     * @return \Schoolbox\LearningAnalytics\Metadata\Base
     */
    private function getMetadata()
    {
        return $this->metadata;
    }

}