<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Jitter;

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
}
