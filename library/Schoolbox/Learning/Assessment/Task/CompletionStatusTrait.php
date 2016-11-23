<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Task;

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
        'completed'    => 3,
        'selfAssessed' => 2,
        'notAttempted' => 1,
    ];

    /**
     * What is the status of this completion? By default, it's incomplete.
     *
     * @var int
     */
    protected $completionStatus = 1;

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
    public function isMarkedAsCompleted()
    {
        return $this->isMarkedAs($this->availableCompletionStatuses['completed']);
    }

    /**
     * Set this task as completed.
     *
     * @return static
     */
    public function markAsCompleted()
    {
        return $this->markAs($this->availableCompletionStatuses['completed']);
    }

    /**
     * Is this task marked as self-assessed?
     *
     * @return bool
     */
    public function isMarkedAsSelfAssessed()
    {
        return $this->isMarkedAs($this->availableCompletionStatuses['selfAssessed']);
    }

    /**
     * Set this task as self-assessed.
     *
     * @return static
     */
    public function markAsSelfAssessed()
    {
        return $this->markAs($this->availableCompletionStatuses['selfAssessed']);
    }

    /**
     * Is this task marked as not yet attempted?
     *
     * @return bool
     */
    public function isMarkedAsNotAttempted()
    {
        return $this->isMarkedAs($this->availableCompletionStatuses['notAttempted']);
    }

    /**
     * Set this task as not yet attempted
     *
     * @return static
     */
    public function markAsNotAttempted()
    {
        return $this->markAs($this->availableCompletionStatuses['notAttempted']);
    }


}
