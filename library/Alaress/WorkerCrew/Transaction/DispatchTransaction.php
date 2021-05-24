<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew\Transaction;

use Alaress\WorkerCrew\DispatcherInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

class DispatchTransaction extends AbstractTransaction
{
    /** @var DispatcherInterface */
    private $dispatcher;

    /** @var string */
    private $command;

    /**
     * Create a new Transaction
     *
     * @param LoopInterface       $loop
     * @param DispatcherInterface $dispatcher
     * @param string              $command
     * @param int                 $cacheInterval - how long to cache for, in seconds
     * @param int                 $size          - the max size of the cache, or 0 for no limit. If this limit is reached, the
     *                                           transaction will execute prematurely
     */
    public function __construct(LoopInterface $loop, DispatcherInterface $dispatcher, $command, $cacheInterval = 1, $size = 0)
    {
        parent::__construct($loop, $cacheInterval, $size);

        $this->dispatcher = $dispatcher;
        $this->command    = $command;
    }


    /**
     * Transaction is ready to go. Execute.
     *
     * @param TimerInterface $timer
     * @param mixed          $data
     * @return void
     */
    protected function execute(TimerInterface $timer, $data)
    {
        $this->dispatcher->enqueue($timer, $this->command, $data);
    }
}
