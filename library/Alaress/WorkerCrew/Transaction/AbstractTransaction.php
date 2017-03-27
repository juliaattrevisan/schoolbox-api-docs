<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\WorkerCrew\Transaction;

use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;

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

    /**
     * Create a new Transaction
     *
     * @param LoopInterface $loop
     * @param int $cacheInterval - how long to cache for, in seconds
     * @param int $size          - the max size of the cache, or 0 for no limit. If this limit is reached, the
     *                             transaction will execute prematurely
     */
    function __construct($loop, $cacheInterval = 1, $size = 0) {
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
    public function setCacheInterval($cacheInterval) {
        $this->cacheInterval = $cacheInterval;
        return $this;
    }


    /**
     * Get the caching interval
     *
     * @return int
     */
    public function getCacheInterval() {
        return $this->cacheInterval;
    }


    /**
     * Set data in this transaction
     *
     * @param string|int $key
     * @param mixed $value
     * @return self
     */
    public function put($key, $value) {
        if(!$this->isTicking()) {
            $this->resetTimer();
        }
        $data = $this->timer->getData() ?: [];
        $data[$key] = $value;
        $this->timer->setData($data);

        // if we've reached our caching limit, fire and reset
        if($this->size && count($data) >= $this->size) {
            $this->flush();
        }

        return $this;
    }


    /**
     * Reset the timer, clearing a pre-existing one if it exists
     */
    private function resetTimer() {
        if(isset($this->timer)) {
            $this->timer->cancel();
        }
        $this->timer = $this->loop->addTimer($this->cacheInterval, function (TimerInterface $timer) {
            $this->execute($timer);
        });
    }


    /**
     * Returns whether or not this transaction is currently active and pending execution
     *
     * @return bool
     */
    private function isTicking() {
        return isset($this->timer) && $this->timer->isActive();
    }

    /**
     * @return int
     */
    public function getCount() {
        return count($this->timer->getData());
    }

    /**
     * Forcibly flush the transaction, regardless of state
     *
     * @return $this
     */
    public function flush()
    {
        if (isset($this->timer)) {
            $this->execute($this->timer);
            $this->timer->cancel();
            $this->timer = null;
        }

        return $this;
    }

    /**
     * Transaction is ready to go. Execute.
     *
     * @param TimerInterface $timer
     * @return void
     */
    protected abstract function execute(TimerInterface $timer);

}