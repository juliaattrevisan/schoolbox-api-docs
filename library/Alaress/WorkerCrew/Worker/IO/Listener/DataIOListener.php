<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew\Worker\IO\Listener;

use Alaress\Async\Listener\Stream\StreamEvent;
use Alaress\WorkerCrew\Pool;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\PoolInterface;
use Alaress\WorkerCrew\TaskResponseHandlerInterface;
use Alaress\WorkerCrew\Worker\Worker;
use Exception;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

class DataIOListener extends AbstractIOStreamListener
{

    /** @var TaskResponseHandlerInterface */
    protected $handler;

    /** @var DispatchStrategyInterface */
    protected $strat;

    /** @var PoolInterface */
    protected $pool;


    /**
     * @param LoggerInterface              $log
     * @param PoolInterface                $pool
     * @param DispatchStrategyInterface    $strat
     * @param TaskResponseHandlerInterface $responseHandler
     */
    public function __construct(
        LoggerInterface $log,
        PoolInterface $pool,
        DispatchStrategyInterface $strat,
        TaskResponseHandlerInterface $responseHandler
    ) {
        parent::__construct($log);
        $this->handler = $responseHandler;
        $this->strat   = $strat;
        $this->pool    = $pool;
    }


    /**
     * Sent when the stream has been closed for any reason
     *
     * @param StreamEvent $e
     * @return void
     */
    public function streamClosed(StreamEvent $e)
    {
        $this->log->debug(sprintf('dstream [:%d] close', $e->getStream()));

        try {
            $pid = $this->pool->getProcessIdByResource($e->getStream());

            $this->log->notice("Worker {{$pid}} datastream closed: ensuring worker terminated");
            $this->pool->releaseWorker($pid, Pool::RELEASE_HARD);
        } catch (UnexpectedValueException $e) {
            $this->log->critical("Unexpected stream supplied, cannot locate PID", ['exception' => $e]);
        }
    }


    /**
     * Sent when data is received through the stream
     *
     * @param StreamEvent $e
     * @return void
     */
    public function dataReceived(StreamEvent $e)
    {
        $this->log->debug(sprintf('dstream [:%d] data', $e->getStream()));

        $pool = $this->pool;

        try {
            $pid  = $pool->getProcessIdByResource($e->getStream());
            $task = $pool->getCurrentTaskByProcessId($pid);
            $data = $e->getData();
        } catch (UnexpectedValueException $e) {
            $this->log->critical("Unexpected stream supplied, cannot locate PID", ['exception' => $e]);
            return;
        }

        $status = $pool->setWorkerStatus($pid, $pool->calculateState($data));

        switch ($status) {
            case Worker::READY: // ready for another task
                $this->log->info("Worker {{$pid}} READY for new task");
                break;

            case Worker::BUSY: // data sent, but not finished yet
                $this->log->debug("Worker {{$pid}} still BUSY working");
                $task->append($data);
                break;

            case Worker::ERROR: // still alive, but cannot accept more tasks
                $this->log->warning("Worker {{$pid}} in ERROR");
                break;

            case Worker::ENDED: // process has closed. Primarily this will happen if fatal errors are hit
                $this->log->info("Worker {{$pid}} has ENDED");
                $pool->releaseWorker($pid);
                break;

            case Worker::DONE: // job done, ready for another task
                $this->log->info("Worker {{$pid}} is DONE, parsing response");
                // append the final batch of data
                $task->append($data);

                try {
                    // pass to the registered response handler
                    $this->handler->handleResponse(
                        $task->getIdentifier(),
                        $task->getCommand(),
                        $this->strat->decodeRecords($task->getData(false))
                    );
                    $newStatus = $pool->setWorkerStatus($pid, Worker::READY);

                    if ($newStatus == Worker::READY) {
                        $this->log->info("Worker {{$pid}} READY for new task");
                    }
                } catch (Exception $e) {
                    // your reactor will explode if you don't catch exceptions coming from your response handler
                    $this->strat->handleException($e, $this->log);
                    $this->log->info("Response from worker {{$pid}} failed to parse, still DONE");
                }

                // regardless of whether or not the handling of the response succeeded, we need to ditch this message
                $this->strat->pdequeue($task->getIdentifier());
                break;
        }

        // if we can accept, emit READY (to be caught by dispatcher)
        if ($pool->canAccept()) {
            $pool->emit('ready');
        }
    }
}
