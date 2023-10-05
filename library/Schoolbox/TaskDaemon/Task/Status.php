<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon\Task;

/**
 * Statuses which may be set for a task's completion.
 */
enum Status: string
{
    /**
     * Status set for the successful execution of a task.
     *
     * If the receiver receives a response with this status, it should
     * acknowledge the task as complete.
     */
    case SUCCESS = 'success';

    /**
     * Status set for the failed execution of a task.
     *
     * If the receiver receives a response with this status, it should
     * immediately reject the task and mark it as failed.
     *
     * This differs from `STATUS_ERROR` in that this should be considered a
     * definitive failure of the task: re-attempting it will not cause it to
     * succeed.
     */
    case FAILURE = 'failure';

    /**
     * Status set for a task which raised an unexpected error during execution.
     *
     * If the receiver receives a response with this status, it should:
     * * re-attempt the task, if it has been attempted no more than the maximum
     *   number of attempts
     * * otherwise, reject the task and mark it as error
     *
     * This differs from `STATUS_FAILED` in that this should be considered a
     * temporary error: re-attempting the task may result in success.
     */
    case ERROR = 'error';

    /**
     * Status set for a task which is still pending: it is either still running,
     * or has not yet started.
     *
     * If the receiver receives a response with this status, it should:
     * * re-attempt the task, if it has been attempted no more than the maximum
     *   number of attempts
     * * otherwise, reject the task and mark it as still pending (so that it may
     *   be investigated further)
     */
    case PENDING = 'pending';
}
