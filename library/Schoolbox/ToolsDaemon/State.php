<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\ToolsDaemon;

/**
 * Possible states for a job request.
 *
 * Maps to Celery's task states:
 * @see https://docs.celeryq.dev/en/stable/userguide/tasks.html#built-in-states
 */
enum State : string
{
    /**
     * The job is waiting to be executed, or is unknown because it doesn't
     * exist.
     *
     * @see https://docs.celeryq.dev/en/stable/userguide/tasks.html#pending
     */
    case PENDING = 'PENDING';

    /**
     * The job has been started.
     *
     * Note this is not tracked by default, but is within the tools daemon.
     *
     * @see https://docs.celeryq.dev/en/stable/userguide/tasks.html#started
     */
    case STARTED = 'STARTED';

    /**
     * The job has completed successfully.
     *
     * @see https://docs.celeryq.dev/en/stable/userguide/tasks.html#success
     */
    case SUCCESS = 'SUCCESS';

    /**
     * The job has failed to complete successfully.
     *
     * @see https://docs.celeryq.dev/en/stable/userguide/tasks.html#failure
     */
    case FAILURE = 'FAILURE';

    /**
     * The job is currently being retried.
     *
     * @see https://docs.celeryq.dev/en/stable/userguide/tasks.html#retry
     */
    case RETRY = 'RETRY';

    /**
     * The job was requested, but has been revoked.
     *
     * Currently not possible within the tools daemon?
     *
     * @see https://docs.celeryq.dev/en/stable/userguide/tasks.html#revoked
     */
    case REVOKED = 'REVOKED';

    /**
     * Any unexpected state, not covered by any of the above.
     */
    case UNKNOWN = 'UNKNOWN';

    /**
     * Has the task finished running?
     *
     * @return bool
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::SUCCESS, self::FAILURE, self::REVOKED], true);
    }
}
