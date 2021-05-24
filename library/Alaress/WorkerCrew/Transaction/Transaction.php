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

class Transaction extends AbstractTransaction
{

    /** @var callable */
    private $callback;

    /**
     * Create a new Transaction
     *
     * @param LoopInterface $loop
     * @param callable      $callback
     * @param int           $cacheInterval - how long to cache for, in seconds
     * @param int           $size          - the max size of the cache, or 0 for no limit. If this limit is reached, the
     *                                     transaction will execute prematurely
     */
    public function __construct(LoopInterface $loop, callable $callback, $cacheInterval = 1, $size = 0)
    {
        parent::__construct($loop, $cacheInterval, $size);
        $this->callback = $callback;
    }


    /**
     * @param TimerInterface $timer
     * @param mixed          $data
     * @return void
     */
    protected function execute(TimerInterface $timer, $data)
    {
        call_user_func($this->callback, $data);
    }
}
