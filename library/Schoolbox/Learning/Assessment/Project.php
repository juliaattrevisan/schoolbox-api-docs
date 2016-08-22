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
use \Schoolbox\Learning\Summator\ISummator;
use \Schoolbox\Learning\Assessment\Task\Completion\Model as TaskCompletion;
use \Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;

class Project extends AbstractAssessment
{

    /**
     * @var Collection
     */
    protected $assessables;

    /**
     * Summation strategy
     *
     * @var ISummator
     */
    protected $summator;

    /**
     * Project constructor.
     *
     * @param ISummator $summator
     */
    public function __construct(ISummator $summator)
    {
        $this->setAssessables(new Collection());
        $this->setSummator($summator);
    }

    /**
     * Calculate the final mark for this IAssessable
     *
     * Projects use Summator strategies to determine final marks
     * for a Collection of Assessable instances
     */
    public function calculateMark()
    {
        return $this->getSummator()->summate($this->getAssessables());
    }

    /**
     * Calculate how many IAssessable instances have a non-null mark value
     */
    protected function numMarked()
    {
        $numMarked = 0;

        if ($this->getAssessables()->count()) {
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

    /**
     * Get summation strategy
     *
     * @return ISummator
     */
    public function getSummator()
    {
        return $this->summator;
    }

    /**
     * Set a summation strategy
     *
     * @param ISummator $summator
     * @return self
     */
    public function setSummator($summator)
    {
        $this->summator = $summator;
        return $this;
    }
}