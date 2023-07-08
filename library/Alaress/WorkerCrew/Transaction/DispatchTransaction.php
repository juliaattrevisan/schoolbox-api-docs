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
     * Moved from Routing\Subscription|Handler
     */
    private const TRANSACTION_CACHE_TIMEOUT = 0.5;
    private const TRANSACTION_COUNT_LIMIT   = 80;

    /**
     * Create a new Transaction
     *
     * @param LoopInterface       $loop
     * @param DispatcherInterface $dispatcher
     * @param string              $command
     */
    public function __construct(LoopInterface $loop, DispatcherInterface $dispatcher, $command)
    {
        parent::__construct($loop, self::TRANSACTION_CACHE_TIMEOUT, self::TRANSACTION_COUNT_LIMIT);

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
