<?php
/**
 * Project
 *
 * Composite Design Pattern
 *
 * This contains a Collection of IAssessables such as Due Work,
 * Quiz and Tasks.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

use \Schoolbox\Collection;
use \Schoolbox\Learning\Assessment\Task\Completion\Model as TaskCompletion;
use \Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;

class Project extends AbstractAssessment
{

    /**
     * @var Collection
     */
    protected $assessables;

    /**
     * Project constructor.
     */
    public function __construct()
    {
        /* I know Dr hates hook methods, so do this instead
         */
        parent::__construct();

        $this->setAssessables(new Collection());
    }

    /**
     * Calculate how many IAssessable instances have a non-null mark value
     */
    protected function numMarked()
    {
        $numMarked = 0;

        foreach ($this->getAssessables() as $currentAssessable) {

            /** @var AbstractResponse $currentResponse */
            foreach ($currentAssessable->getAllResponses() as $currentResponse) {

                /* Tasks have a special treatment in this context
                 * Tasks aren't marked
                 */
                if ($currentResponse instanceof TaskCompletion) {

                    /** @var TaskFeedback $feedback */
                    $feedback = $currentResponse->findMostRecentFeedback();
                }
                else {

                    /* Find the latest Feedback instance that contained a Mark
                     */
                    $feedback = $currentResponse->findMostRecentNonNullFeedback();
                }

                if ($feedback) {
                    $numMarked++;
                }
            }
        }

        return $numMarked;
    }

    /**
     * Calculate how many IAssessable instances are in this Project
     *
     * @return int
     */
    protected function numAssessables()
    {
        return $this->getAssessables()->count();
    }

    /**
     * Calculate percentage of a Project that has been marked
     *
     * @return int
     */
    public function calculatePercentageMarked()
    {
        if ($this->numAssessables()) {
            return 100 * ($this->numMarked() / $this->numAssessables());
        }

        return 0;
    }

    /**
     * Calculate the number of Feedbacks that have been Published
     * to Students
     *
     * @return int
     */
    protected function numPublished()
    {
        $numPublished = 0;

        foreach ($this->getAssessables() as $currentAssessable) {

            /** @var AbstractResponse $currentResponse */
            foreach ($currentAssessable->getAllResponses() as $currentResponse) {

                /* Tasks have a special treatment in this context
                 * Tasks aren't marked
                 */
                if ($currentResponse instanceof TaskCompletion) {

                    /** @var TaskFeedback $feedback */
                    $feedback = $currentResponse->findMostRecentFeedback();
                }
                else {

                    /* Find the latest Feedback instance that contained a Mark
                     */
                    $feedback = $currentResponse->findMostRecentNonNullFeedback();
                }

                if ($feedback && $feedback->isPublished()) {
                    $numPublished++;
                }
            }
        }

        return $numPublished;
    }

    /**
     * Calculate percentage of a Project that has been Published
     *
     * @return int
     */
    public function calculatePercentagePublished()
    {
        if ($this->numAssessables()) {
            return 100 * ($this->numPublished() / $this->numAssessables());
        }

        return 0;
    }

    /**
     * @inheritdoc
     *
     * @return string
     */
    public function getTypeName()
    {
        return 'Project';
    }

    /* Getters/Setters
     */

    /**
     * @return Collection
     */
    public function getAssessables()
    {
        return $this->assessables;
    }

    /**
     * @param Collection $assessables
     * @return self
     */
    public function setAssessables($assessables)
    {
        $this->assessables = $assessables;
        return $this;
    }
}