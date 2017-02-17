<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Task\Completion;

use \Schoolbox\Learning\Assessment\AbstractResponse;
use Schoolbox\Learning\Assessment\Task\CompletionStatusTrait;
use Schoolbox\Learning\Assessment\Task\ICompletion;
use Schoolbox\Learning\Assessment\Task\Model as TaskModel;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;

/**
 * Class Model
 *
 * @method TaskModel getAssessable()
 */
class Model extends AbstractResponse implements ICompletion
{
    use CompletionStatusTrait;

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

    /**
     * Does this IResponse have feedback?
     *
     * Special case for task completion - a teacher has given feedback if they've marked as completed.
     *
     * TODO this separate implementation should be removed when SA-1667 is implemented
     *      (SA-1667 is the refactor of the task completion/feedback stack)
     *
     * @return bool
     */
    public function hasFeedback()
    {
        return $this->isMarkedAsTeacherAssessed();
    }

    /* Tasks always return Null marks for Feedbacks
     */

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
        $taskUser    = $this->getSubmittedOnBehalfOf();

        // Only participants in task
        if (!$this->getAssessable()->isParticipant($taskUser)) {
            throw new \UnexpectedValueException("User is not a participant in this task");
        }

        // Can't create a new completion with the same status as most recent
        if (empty($this->getId())) {
            $mostRecentResponse = $this->getAssessable()->getParticipant($taskUser);
            if ($mostRecentResponse instanceof Model
                && $mostRecentResponse->isMarkedAs($this->getCompletionStatus())
            ) {
                throw new \UnexpectedValueException("This user already has this status for this task");
            }
        }

        // Can't set own work to completed, or self-assess if task doesn't allow it
        if ($currentUser->getId() == $taskUser->getId()) {
            if ($this->isMarkedAsTeacherAssessed()) {
                throw new \UnexpectedValueException("Users may not complete a task themselves");
            }

            if ($this->isMarkedAsSelfAssessed() && $this->getAssessable()->isTeacherAssessed()) {
                throw new \UnexpectedValueException("Users may not self-assess a task themselves, if the task is teacher-assessed only");
            }
        }
        // If a teacher is marking a student, the teacher must have write+ perm to the class
        else {
            $classFolderId = $this->getAssessable()->getBaseContext()->getId();
            if(Access::getInstance()->getFolderAccess($currentUser, [$classFolderId]) < UserAccess::ACCESS_WRITE){
                throw new \UnexpectedValueException("Teachers may only mark tasks on classes where they have write permissions.");
            }
        }

        // Anything else is fine
        return true;
    }
}
