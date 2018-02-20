<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Task\Response;

use Schoolbox\Assessment\Course\Task\Completion;
use \Schoolbox\Learning\Assessment\AbstractResponse;
use Schoolbox\Learning\Assessment\Task\Model as Task;

/**
 * @method Task getAssessable()
 */
class Model extends AbstractResponse
{
    /**
     * Was the task marked as 'complete' or 'incomplete' ? (Regardless of who marked it)
     *
     * @var bool
     */
    private $markedComplete;

    /**
     * Did the student self-assess or is this a teacher submission-on-behalf-of ?
     *
     * @return bool
     */
    public function isSelfAssessed()
    {
        return $this->getSubmittedBy()->getId() == $this->getSubmittedOnBehalfOf()->getId();
    }


    /**
     * This response is submitted (no longer a draft)
     *
     * @return bool
     */
    public function isSubmitted()
    {
        return true;
    }


    /**
     * Convenience method.
     *
     * Intentionally no setter(). That would potentially require implicit mutation of the submittedBy and submittedFor values.
     *
     * @return int
     */
    public function getCompletionStatus()
    {
        if(!$this->getMarkedComplete()){
            return Completion::STATUS_INCOMPLETE;
        }

        return $this->hasFeedback()
            ? Completion::STATUS_TEACHER_ASSESSED_COMPLETE
            : Completion::STATUS_SELF_ASSESSED_COMPLETE;
    }


    /**
     * Human-intuitive version of mapper-compatible getMarkedComplete()
     *
     * @return bool
     */
    public function isMarkedComplete()
    {
        return $this->getMarkedComplete();
    }


    /* Getters/Setters
     */

    /**
     * @return bool
     */
    public function getMarkedComplete()
    {
        return $this->markedComplete;
    }

    /**
     * @param bool $markedComplete
     * @return $this
     */
    public function setMarkedComplete($markedComplete)
    {
        $this->markedComplete = $markedComplete;
        return $this;
    }

    /**
     * Completions are submitted at the updatedAt value
     * in case the Completion is modified by the Student
     *
     * @return \Alaress_Date_Format
     */
    public function getSubmittedAt()
    {
        return $this->getCreatedAt();
    }

}
