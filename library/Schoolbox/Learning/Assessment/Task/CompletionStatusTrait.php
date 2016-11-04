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
     * Is this task marked as completed?
     *
     * @return bool
     */
    public function isMarkedAsCompleted()
    {
        return $this->completionStatus == $this->availableCompletionStatuses['completed'];
    }

    /**
     * Set this task as completed.
     *
     * @return static
     */
    public function markAsCompleted()
    {
        $this->completionStatus = $this->availableCompletionStatuses['completed'];
        return $this;
    }

    /**
     * Is this task marked as self-assessed?
     *
     * @return bool
     */
    public function isMarkedAsSelfAssessed()
    {
        return $this->completionStatus == $this->availableCompletionStatuses['selfAssessed'];
    }

    /**
     * Set this task as self-assessed.
     */
    public function markAsSelfAssessed()
    {
        $this->completionStatus = $this->availableCompletionStatuses['selfAssessed'];
    }

    /**
     * Is this task marked as not yet attempted?
     *
     * @return bool
     */
    public function isMarkedAsNotAttempted()
    {
        return $this->completionStatus == $this->availableCompletionStatuses['notAttempted'];
    }

    /**
     * Set this task as not yet attempted
     */
    public function markAsNotAttempted()
    {
        $this->completionStatus = $this->availableCompletionStatuses['notAttempted'];
    }


}
