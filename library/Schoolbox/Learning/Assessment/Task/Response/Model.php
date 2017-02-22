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
     * Did a teacher submission-on-behalf-of the student ?
     *
     * @return bool
     */
    public function isTeacherAssessed()
    {
        return !$this->isSelfAssessed();
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

        return $this->isSelfAssessed()
            ? Completion::STATUS_SELF_ASSESSED_COMPLETE
            : Completion::STATUS_SELF_ASSESSED_COMPLETE;
    }




    /**
     * Is this completion saveable in its current state?
     *
     * * Completions cannot be created for users not participating in the task.
     * * Nobody may create a new completion that is the same status as the most
     *   recent completion for this user.
     * * Participants may not set their own work to completed.
     * * Participants may not set their own work to self-assessed,
     *   if the task is teacher-assessed only.
     *
     * This method assumes that the current user has permission to create
     * completions for this task.
     *
     * @return bool
     * @throws \UnexpectedValueException if the completion isn't saveable
     */
    public function assertSaveability()
    {
        $currentUser = $this->getSubmittedBy();
        $student     = $this->getSubmittedOnBehalfOf();

        // Only participants in task
        if (!$this->getAssessable()->isParticipant($student)) {
            throw new \UnexpectedValueException("Student is not a participant in this task");
        }

        // Can't create a new completion with the same status as most recent
        if (empty($this->getId())) {
            $mostRecentResponse = $this->getAssessable()->getParticipant($student)->findMostRecentResponse();
            if ($mostRecentResponse instanceof static
                && $mostRecentResponse->getMarkedComplete() == $this->getMarkedComplete()
                && $mostRecentResponse->isSelfAssessed() == $this->isSelfAssessed()
            ) {
                 throw new \UnexpectedValueException("This student already has this status ({$mostRecentResponse->getCompletionStatus()}) for this task");
            }
        }

        // Is the student self-assessing ?
        if ($currentUser->getId() == $student->getId()) {

            // Is the task self-assessable ?
            if (!$this->getAssessable()->isSelfAssessable()) {
                throw new \UnexpectedValueException("Users may not self-assess a task themselves, if the task is not self-assessable");
            }

            // Is the task already marked by the teacher as complete ?
            if (!$this->isSelfAssessed() && $this->getMarkedComplete()) {
                throw new \UnexpectedValueException("Students may not change a task completion status if the teacher has marked it complete.");
            }
        }

        // Anything else is fine
        return true;
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
