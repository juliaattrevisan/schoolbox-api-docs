<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew\Transaction;


use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;

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
     * @return void
     */
    protected function execute(TimerInterface $timer)
    {
        call_user_func($this->callback, $timer);
    }

}