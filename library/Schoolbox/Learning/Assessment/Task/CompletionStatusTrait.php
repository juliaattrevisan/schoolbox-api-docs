<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Task;

use Schoolbox\Assessment\Course\Task\Completion;

/**
 * Trait for setting and querying completion statuses.
 */
trait CompletionStatusTrait
{
    /**
     * All possible completion statuses.
     *
     * @var array
     */
    protected $availableCompletionStatuses = [
        Completion::COMPLETION_STATUS_INCOMPLETE,
        Completion::COMPLETION_STATUS_SELF_ASSESSED,
        Completion::COMPLETION_STATUS_TEACHER_ASSESSED,
    ];

    /**
     * What is the status of this completion? By default, it's not attempted.
     *
     * @var int
     */
    protected $completionStatus = Completion::COMPLETION_STATUS_INCOMPLETE;

    /**
     * Compares this task's completion status against another input.
     *
     * @param int $completionStatus
     * @return bool
     */
    public function isMarkedAs($completionStatus)
    {
        return $this->completionStatus == $completionStatus;
    }

    /**
     * Set this task as this status.
     *
     * @param int $completionStatus
     * @return static
     * @throws \UnexpectedValueException if $completionStatus isn't a value in $availableCompletionStatuses
     */
    public function markAs($completionStatus)
    {
        if (!in_array($completionStatus, $this->availableCompletionStatuses)) {
            throw new \UnexpectedValueException("$completionStatus is not a valid completion status");
        }
        $this->completionStatus = $completionStatus;
        return $this;
    }

    /**
     * Is this task marked as completed?
     *
     * @return bool
     */
    public function isMarkedAsTeacherAssessed()
    {
        return $this->isMarkedAs(Completion::COMPLETION_STATUS_TEACHER_ASSESSED);
    }

    /**
     * Set this task as completed.
     *
     * @return static
     */
    public function markAsTeacherAssessed()
    {
        return $this->markAs(Completion::COMPLETION_STATUS_TEACHER_ASSESSED);
    }

    /**
     * Is this task marked as self-assessed?
     *
     * @return bool
     */
    public function isMarkedAsSelfAssessed()
    {
        return $this->isMarkedAs(Completion::COMPLETION_STATUS_SELF_ASSESSED);
    }

    /**
     * Set this task as self-assessed.
     *
     * @return static
     */
    public function markAsSelfAssessed()
    {
        return $this->markAs(Completion::COMPLETION_STATUS_SELF_ASSESSED);
    }

    /**
     * Is this task marked as not yet attempted?
     *
     * @return bool
     */
    public function isMarkedAsIncomplete()
    {
        return $this->isMarkedAs(Completion::COMPLETION_STATUS_INCOMPLETE);
    }

    /**
     * Set this task as not yet attempted
     *
     * @return static
     */
    public function markAsIncomplete()
    {
        return $this->markAs(Completion::COMPLETION_STATUS_INCOMPLETE);
    }

    /**
     * Gets completion status
     *
     * Use `markAs()` methods in lieu of conventional setter
     *
     * @return int
     */
    public function getCompletionStatus()
    {
        return $this->completionStatus;
    }
}
