<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew\Worker\IO\Listener;

use Alaress\Async\Listener\Stream\StreamEvent;
use Alaress\WorkerCrew\PoolInterface;
use Alaress\WorkerCrew\TaskResponseHandlerInterface;
use Alaress\WorkerCrew\Worker\Task;
use Exception;
use OutOfBoundsException;
use Psr\Log\LoggerInterface;

class ErrorIOListener extends LogIOListener
{

    /** @var PoolInterface */
    protected $pool;

    /** @var TaskResponseHandlerInterface */
    protected $handler;


    /**
     * @param LoggerInterface              $log
     * @param PoolInterface                $pool
     * @param TaskResponseHandlerInterface $responseHandler
     */
    public function __construct(LoggerInterface $log, PoolInterface $pool, TaskResponseHandlerInterface $responseHandler)
    {
        parent::__construct($log);
        $this->pool    = $pool;
        $this->handler = $responseHandler;
    }


    /**
     * Sent when data is received through the stream
     *
     * @param StreamEvent $e
     * @return void
     */
    public function dataReceived(StreamEvent $e)
    {
        if (strpos($e->getData(), 'SIS connector') > 0) {
            $this->log->error('[ErrorIOListener] Caught SIS connector issue.');
        } else {
            $this->log->error($e->getData());
        }

        // draw the hard line: you get data down this pipe, say goodbye to the worker
        try {
            $pid  = $this->pool->getProcessIdByResource($e->getStream());
            $task = $this->pool->getCurrentTaskByProcessId($pid);
            $data = $e->getData();

            $this->pool->releaseWorker($this->pool->getProcessIdByResource($e->getStream()));
        } catch (OutOfBoundsException $oob) {
            $this->log->warning("Could not release worker with estream [:{$e->getStream()}]");
            return;
        }

        try {
            if (!$task instanceof Task) {
                throw new OutOfBoundsException("Worker {{$pid}} had no task");
            }

            // pass to the registered response handler
            $this->handler->handleError(
                $task->getIdentifier(),
                $task->getCommand(),
                $data
            );
        } catch (Exception $e) {
            // your reactor will explode if you don't catch exceptions coming from your response handler
            $this->log->info("Error from worker {{$pid}} failed to parse, worker still gone");
        }
    }
}
