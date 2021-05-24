<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
     * Maximum number of attempts to make
     *
     * @var int
     */
    private $maxAttempts;

    /**
     * Constructor
     *
     * @param IGenerator $rng
     * @param int        $maxAttempts How many attempts to make
     * @param int        $baseTimeout A base number of milliseconds to wait.
     *                                The actual wait will be based on this,
     *                                and increase roughly exponentially.
     */
    public function __construct(IGenerator $rng, $maxAttempts = 10, $baseTimeout = 500)
    {
        $this->rng = $rng;
        $this->baseTimeout = $baseTimeout;
        $this->maxAttempts = $maxAttempts;
    }

    /**
     * Is `$attempts` above the max number of allowable attempts?
     *
     * @param int $attempts
     * @return bool
     */
    public function tooManyAttempts($attempts)
    {
        return $attempts >= $this->maxAttempts;
    }

    /**
     * Calculates jitter based on amount of time.
     *
     * If the last request failed, wait a little while before trying again.
     * The amount of time to wait increases with the number of attempts.
     *
     * @param int $attempts The number of attempts made so far
     * @return int Amount of jitter
     * @throws MaxAttemptsExceededException if `$attempts` is more than allowed
     */
    public function calculateJitter($attempts)
    {
        if ($attempts <= 0) {
            return 0;
        }

        // Too many failures? error out
        if ($this->tooManyAttempts($attempts)) {
            throw new MaxAttemptsExceededException(sprintf('Maximum of %d attempts exceeded', $this->maxAttempts));
        }

        $sleep = $this->baseTimeout * min($this->maxAttempts, pow(2, ($attempts - 1)));
        return $sleep * $this->rng->generate();
    }

    /**
     * Built-in retry of `$callable` until it either returns or too many
     * attempts have been made.
     *
     * @param callable $callable
     * @return mixed Whatever `$callable` returns if it doesn't exception out
     * @throws Exception|Throwable if `$callable` never returned, rethrow the
     *                             last exception it threw
     */
    public function retry(callable $callable)
    {
        $attempts = 0;
        $lastException = null;
        while (true) {
            try {
                $jitter = $this->calculateJitter($attempts);
            } catch (MaxAttemptsExceededException $e) {
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
                $attempts++;
            }
        }

        // Should never get here, but:
        return null;
    }
}
