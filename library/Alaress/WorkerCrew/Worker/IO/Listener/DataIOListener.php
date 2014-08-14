<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew\Worker\IO\Listener;


use Alaress\Async\Listener\Stream\StreamEvent;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\TaskResponseHandlerInterface;
use Alaress\WorkerCrew\Worker\Worker;
use Alaress\WorkerCrew\Worker\WorkerBundleInterface;
use Evenement\EventEmitter;
use Exception;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

class DataIOListener extends AbstractIOStreamListener
{

    /** @var WorkerBundleInterface */
    protected $workerBundle;

    /** @var TaskResponseHandlerInterface */
    protected $responseHandler;

    /** @var DispatchStrategyInterface */
    protected $strat;

    /** @var EventEmitter */
    protected $emitter;


    /** */
    public function __construct(
        LoggerInterface $log,
        WorkerBundleInterface $workers,
        DispatchStrategyInterface $strat,
        TaskResponseHandlerInterface $responseHandler,
        EventEmitter $emitter
    ) {
        parent::__construct($log);
        $this->workerBundle    = $workers;
        $this->responseHandler = $responseHandler;
        $this->strat           = $strat;
        $this->emitter         = $emitter;
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
            $pid = $this->workerBundle->getProcessIdByResource($e->getStream());
            $this->workerBundle->getByProcessId($pid)
                               ->setStatus(Worker::ENDED);
            $this->log->notice("Worker $pid killed");
        } catch(UnexpectedValueException $e) {
            $this->log->critical("Unexpected stream supplied, cannot locate PID");
        }

        $this->strat->cleanup($this->workerBundle);
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

        try {
            $pid    = $this->workerBundle->getProcessIdByResource($e->getStream());
            $worker = $this->workerBundle->getByProcessId($pid);
            $task   = $worker->getTask();
            $data   = $e->getData();
        } catch(UnexpectedValueException $e) {
            $this->log->critical("Unexpected stream supplied, cannot locate PID");
            return;
        }

        $state = $this->strat->checkState($data);

        switch ($state) {
            case Worker::READY: // ready for another task
                $this->log->info("Worker '$pid' ready for new task");
                $worker->setStatus(Worker::READY);
                break;

            case Worker::BUSY: // data sent, but not finished yet
                $this->log->debug("Worker '$pid' still working");
                $worker->appendData($data);
                break;

            case Worker::ERROR: // still alive, but cannot accept more tasks
                $this->log->notice("Worker '$pid' in error");
                $worker->setStatus(Worker::BUSY);
                break;

            case Worker::ENDED: // process has closed. Primarily this will happen if fatal errors are hit
                $this->log->info("Worker '$pid' killed");
                $worker->setStatus(Worker::ENDED);
                break;

            case Worker::DONE: // job done, ready for another task
                $this->log->info("Worker '$pid' complete, ready for new task");
                $worker->appendData($data)
                       ->markTaskComplete();

                try {
                    // pass to the registered response handler
                    $this->responseHandler->handleResponse(
                        $task->getIdentifier(),
                        $task->getCommand(),
                        $this->strat->decodeRecords($task->getData(false))
                    );
                } catch(Exception $e) {
                    // your reactor will explode if you don't catch exceptions coming from your response handler
                    $this->strat->handleException($e, $this->log);
                }

                // regardless of whether or not the handling of the response succeeded, we need to ditch this message
                $this->strat->pdequeue($task->getIdentifier());
                break;

        }

        // if we can accept, emit READY (to be caught by dispatcher)
        if ($this->strat->canAccept($this->workerBundle)) {
            $this->emitter->emit('ready');
        }

    }

}