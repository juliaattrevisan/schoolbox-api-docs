<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Jitter;

/**
 * Class Jitter
 */
class Jitter
{
    /**
     * Base timeout, in milliseconds
     *
     * @var int
     */
    private $baseTimeout;

    /**
     * Maximum number of retries
     *
     * @var int
     */
    private $maxRetries;

    /**
     * Constructor
     *
     * @param int $baseTimeout
     * @param int $maxRetries
     */
    public function __construct($maxRetries = 10, $baseTimeout = 500)
    {
        $this->baseTimeout = $baseTimeout;
        $this->maxRetries  = $maxRetries;
    }

    /**
     * Calculates jitter based on amount of time.
     *
     * If the last request failed, wait a little while before trying again.
     * The amount of time to wait increases with the number of retries.
     *
     * @param int $retries
     * @return int A number of milliseconds to sleep for
     * @throws MaxRetriesExceededException if $retries is more than allowed
     */
    public function calculateJitter($retries)
    {
        if ($retries === 0) {
            return 0;
        }

        if ($retries > $this->maxRetries) {
            throw new MaxRetriesExceededException(sprintf('Maximum of %d retries exceeded', $this->maxRetries));
        }

        // Too many failures? error out
        $randmax    = mt_getrandmax();
        $randfloat  = mt_rand(0, $randmax) / $randmax;

        $sleep = min(
            $this->baseTimeout * $this->maxRetries,
            $this->baseTimeout * (2 ^ $retries)
        );

        return $sleep * $randfloat * 1000;
    }
}
