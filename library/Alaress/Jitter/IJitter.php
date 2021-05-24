<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Jitter;

use Exception;
use Throwable;

/**
 * Interface for jitter classes.
 */
interface IJitter
{
    /**
     * Is `$attempts` above the max number of allowable attempts?
     *
     * @param int $attempts
     * @return bool
     */
    public function tooManyAttempts($attempts);

    /**
     * Calculates jitter based on amount of time.
     *
     * If the last request failed, wait a little while before trying again.
     * The amount of time to wait increases with the number of attempts.
     *
     * @param int $attempts
     * @return int Amount of jitter
     * @throws MaxAttemptsExceededException if `$attempts` is more than allowed
     */
    public function calculateJitter($attempts);

    /**
     * Built-in retry of `$callable` until it either returns or too many
     * attempts have been made.
     *
     * @param callable $callable
     * @return mixed Whatever `$callable` returns if it doesn't exception out
     * @throws Exception|Throwable if `$callable` never returned, rethrow the
     *                             last exception it threw
     */
    public function retry(callable $callable);
}
