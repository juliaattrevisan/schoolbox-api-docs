<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Jitter;

use Alaress\Random\IGenerator;
use Exception;
use Throwable;

/**
 * Class Jitter
 */
class Jitter implements IJitter
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
     * @param IGenerator $rng
     * @param int        $maxRetries  How many attempts to make
     * @param int        $baseTimeout A base number of milliseconds to wait.
     *                                The actual wait will be based on this,
     *                                and increase roughly exponentially.
     */
    public function __construct(IGenerator $rng, $maxRetries = 10, $baseTimeout = 500)
    {
        $this->rng = $rng;
        $this->baseTimeout = $baseTimeout;
        $this->maxRetries = $maxRetries;
    }

    /**
     * Is $retries above max retries?
     *
     * @param int $retries
     * @return bool
     */
    public function tooManyRetries($retries)
    {
        return $retries >= $this->maxRetries;
    }

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
    public function calculateJitter($retries)
    {
        if ($retries <= 0) {
            return 0;
        }

        // Too many failures? error out
        if ($this->tooManyRetries($retries)) {
            throw new MaxRetriesExceededException(sprintf('Maximum of %d retries exceeded', $this->maxRetries));
        }

        $sleep = $this->baseTimeout * min($this->maxRetries, pow(2, ($retries - 1)));
        return $sleep * $this->rng->generate();
    }

    /**
     * Built-in retry of `$callable` until it either returns or retries too
     * many times.
     *
     * @param callable $callable
     * @return mixed Whatever `$callable` returns if it doesn't exception out
     * @throws Exception|Throwable if `$callable` never returned, rethrow the
     *                             last exception it threw
     */
    public function retry(callable $callable)
    {
        $retries = 0;
        $lastException = null;
        while (true) {
            try {
                $jitter = $this->calculateJitter($retries);
            } catch (MaxRetriesExceededException $e) {
                if ($lastException instanceof Exception || $lastException instanceof Throwable) {
                    throw $lastException;
                }
                throw $e;
            }
            if ($jitter > 0) {
                usleep($jitter * 1000);
            }
            try {
                return $callable();
            } catch (\Exception $e) {
                $lastException = $e;
                $retries++;
            }
        }

        // Should never get here, but:
        return null;
    }
}
