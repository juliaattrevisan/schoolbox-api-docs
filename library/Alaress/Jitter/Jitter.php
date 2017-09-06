<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Jitter;

use Alaress\Random\IGenerator;

/**
 * Class Jitter
 */
class Jitter
{
    /**
     * A random number generator; injected so it's unit-testable
     *
     * @var IGenerator
     */
    private $rng;

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
    public function __construct(IGenerator $rng, $maxRetries = 10, $baseTimeout = 500)
    {
        $this->rng         = $rng;
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

        // Too many failures? error out
        if ($retries > $this->maxRetries) {
            throw new MaxRetriesExceededException(sprintf('Maximum of %d retries exceeded', $this->maxRetries));
        }

        $sleep = min(
            $this->baseTimeout * $this->maxRetries,
            $this->baseTimeout * (2 ^ $retries)
        );
        return $sleep * $this->rng->generate() * 1000;
    }
}
