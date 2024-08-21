<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew\Transaction;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

abstract class AbstractTransaction implements ITransaction
{
    /** @var LoopInterface */
    private $loop;

    /** @var int */
    private $cacheInterval;

    /** @var TimerInterface */
    private $timer = null;

    /** @var int */
    private $size = 0;

    /** @var array */
    private $data = [];

    /**
     * Create a new Transaction
     *
     * @param LoopInterface $loop
     * @param int $cacheInterval - how long to cache for, in seconds
     * @param int $size          - the max size of the cache, or 0 for no limit. If this limit is reached, the
     *                             transaction will execute prematurely
     */
    function __construct($loop, $cacheInterval = 1, $size = 0)
    {
        $this->loop = $loop;
        $this->cacheInterval = $cacheInterval;
        $this->size = $size;
    }


    /**
     * Set the caching interval
     *
     * @param int $cacheInterval
     * @return self
     */
    public function setCacheInterval($cacheInterval)
    {
        $this->cacheInterval = $cacheInterval;
        return $this;
    }


    /**
     * Get the caching interval
     *
     * @return int
     */
    public function getCacheInterval()
    {
        return $this->cacheInterval;
    }


    /**
     * Set data in this transaction
     *
     * @param string|int $key
     * @param mixed $value
     * @return self
     */
    public function put($key, $value)
    {
        if (!$this->isTicking()) {
            $this->resetTimer();
        }
//        $data = $this->timer->getData() ?: [];
//        $data[$key] = $value;
//        $this->timer->setData($data);
        $this->data[$key] = $value;

        // if we've reached our caching limit, fire and reset
        if ($this->size && count($this->data) >= $this->size) {
            $this->flush();
        }

        return $this;
    }


    /**
     * Reset the timer, clearing a pre-existing one if it exists
     */
    private function resetTimer()
    {
        if (isset($this->timer)) {
            $this->loop->cancelTimer($this->timer);
        }
        $this->timer = $this->loop->addTimer($this->cacheInterval, function (TimerInterface $timer): void {
            $this->execute($timer, $this->data);
            $this->timer = null;
            $this->data  = [];
        });
    }


    /**
     * Returns whether or not this transaction is currently active and pending execution
     *
     * @return bool
     */
    private function isTicking()
    {
        return isset($this->timer);
    }

    /**
     * @return int
     */
    public function getCount()
    {
        return count($this->data);
    }

    /**
     * Forcibly flush the transaction, regardless of state
     *
     * @return $this
     */
    public function flush()
    {
        if (isset($this->timer)) {
            $this->execute($this->timer, $this->data);
            $this->loop->cancelTimer($this->timer);
            $this->timer = null;
            $this->data  = [];
        }

        return $this;
    }

    /**
     * Transaction is ready to go. Execute.
     *
     * @param TimerInterface $timer
     * @param mixed          $data
     * @return void
     */
    abstract protected function execute(TimerInterface $timer, $data);
}
