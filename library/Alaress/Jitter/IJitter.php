<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
     * Is $retries above max retries?
     *
     * @param int $retries
     * @return bool
     */
    public function tooManyRetries($retries);

    /**
     * Calculates jitter based on amount of time.
     *
     * If the last request failed, wait a little while before trying again.
     * The amount of time to wait increases with the number of retries.
     *
     * @param int $retries
     * @return int Amount of jitter
     * @throws MaxRetriesExceededException if $retries is more than allowed
     */
    public function calculateJitter($retries);

    /**
     * Built-in retry of `$callable` until it either returns or retries too
     * many times.
     *
     * @param callable $callable
     * @return mixed Whatever `$callable` returns if it doesn't exception out
     * @throws Exception|Throwable if `$callable` never returned, rethrow the
     *                             last exception it threw
     */
    public function retry(callable $callable);
}
