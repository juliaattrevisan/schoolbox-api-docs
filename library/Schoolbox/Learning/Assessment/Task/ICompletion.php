<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Task;

/**
 * Interface for task completions.
 */
interface ICompletion
{
    /**
     * Compares this task's completion status against another input.
     *
     * @param int $completionStatus
     * @return bool
     */
    public function isMarkedAs($completionStatus);

    /**
     * Set this task as this status.
     *
     * @param int $completionStatus
     * @return static
     * @throws \UnexpectedValueException if $completionStatus isn't a value in $availableCompletionStatuses
     */
    public function markAs($completionStatus);

    /**
     * Is this task marked as completed?
     *
     * @return bool
     */
    public function isMarkedAsTeacherAssessed();

    /**
     * Set this task as completed.
     *
     * @return static
     */
    public function markAsTeacherAssessed();

    /**
     * Is this task marked as self-assessed?
     *
     * @return bool
     */
    public function isMarkedAsSelfAssessed();

    /**
     * Set this task as self-assessed.
     *
     * @return static
     */
    public function markAsSelfAssessed();

    /**
     * Is this task marked as not yet attempted?
     *
     * @return bool
     */
    public function isMarkedAsIncomplete();

    /**
     * Set this task as not yet attempted
     *
     * @return static
     */
    public function markAsIncomplete();

    /**
     * Gets completion status
     *
     * Use `markAs()` methods in lieu of conventional setter
     *
     * @return int
     */
    public function getCompletionStatus();
}
