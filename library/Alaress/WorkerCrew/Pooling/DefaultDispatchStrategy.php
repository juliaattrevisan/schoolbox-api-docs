<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew\Pooling;

use Alaress\WorkerCrew\IPC\AlaressIPCProtocol;
use Alaress\WorkerCrew\IPC\ProtocolInterface;
use Alaress\WorkerCrew\Worker\Worker;
use Alaress\WorkerCrew\Worker\WorkerArray;
use Alaress\WorkerCrew\Worker\WorkerBundleInterface;
use Exception;
use InvalidArgumentException;
use OutOfBoundsException;
use PDOException;
use Psr\Log\LoggerInterface;
use SplQueue;

class DefaultDispatchStrategy implements DispatchStrategyInterface
{

    const MIN_WORKERS  = 1;
    const MAX_WORKERS  = 6;
    const MAX_FLYWHEEL = 0;

    const QUEUE_DELAY     = 1;
    const QUEUE_SIZE_WARN = PHP_INT_MAX;
    const QUEUE_SIZE_MAX  = PHP_INT_MAX;

    const IDLE_TIMEOUT = 60000; // 1 minute

    const WORKER_SOFT_TIMEOUT = 300000; // 5 minutes
    const WORKER_HARD_TIMEOUT = 1200000; // 20 minutes

    const TASK_FETCH     = 'fetch';
    const TASK_PUBLISH   = 'publish';
    const TASK_DIGEST    = 'digest';
    const TASK_TIMETABLE = 'timetable';

    protected $flywheelSpins;


    /** @var AlaressIPCProtocol */
    protected $protocol;


    /** @var string */
    protected $flags;


    /**
     * @param string $flags
     */
    public function __construct($flags = '')
    {
        $this->flywheelSpins = self::MAX_FLYWHEEL;
        $this->protocol      = new AlaressIPCProtocol();
        $this->flags         = $flags;
    }


    /**
     * Get the name of the strategy currently being used
     *
     * @return string
     */
    public function getName()
    {
        return 'Default';
    }


    /**
     * Returns the IPC protocol that this strategy employs
     *
     * @return ProtocolInterface
     */
    public function getProtocol()
    {
        return $this->protocol;
    }


    /**
     * Returns the command that will be executed each time a worker is spawned by this strategy.
     *
     * @return string
     */
    public function getCommand()
    {
        return SITE_root . "/tools/socket/worker.php";
    }


    /**
     * Returns whether or not a new command can be accepted.
     *
     * @param WorkerBundleInterface $workers
     * @return bool
     */
    public function canAccept($workers)
    {
        return $this->getAvailableWorker($workers, 0) != -1;
    }


    /**
     * Return:
     *   the PID of an available worker,
     *   0 if none are available but it is permissible to create one, or
     *   -1 if none are available, and no more may be spawned right now.
     *
     * @param WorkerBundleInterface $workers
     * @param int                   $remainingTaskCount
     * @return int
     */
    public function getAvailableWorker($workers, $remainingTaskCount)
    {
        // if we haven't got the min number of workers, favour starting a new one
        if ($workers->countWhere(~(Worker::ENDED & Worker::ERROR)) < self::MIN_WORKERS) {
            return 0;
        }

        // if a worker is free, yes we can accept
        try {
            $free = $workers->getPidWhere(Worker::READY);
            return $free;
        } catch (OutOfBoundsException $e) {
            // ok, so there's none of those
        }

        // if number of workers THAT ARE NOT ENDED OR ERRORED is below the cap, spawn a new one
        if ($workers->countWhere(~(Worker::ENDED & Worker::ERROR)) < self::MAX_WORKERS) {
            return 0;
        }

        return -1;
    }


    /**
     * Return the list of commands that can be delegated to workers
     *
     * @return array
     */
    public function getSupportedActions()
    {
        return array(self::TASK_FETCH, self::TASK_PUBLISH, self::TASK_DIGEST, self::TASK_TIMETABLE);
    }


    /**
     * Returns the flags that should be used for workers
     *
     * @return string
     */
    public function getFlags()
    {
        return $this->flags;
    }


    /**
     * Evaluate the segment of data given and return a Worker STATE based on it
     *
     * @param string $data
     * @return int
     */
    public function checkState($data)
    {
        // no data: this pipe is closed
        if (strlen($data) == 0) {
            return Worker::ENDED;
        }

        // rejected: ready for more
        if ($this->protocol->isRejected($data)) {
            return Worker::DONE;
        }

        // job cancelled: ready for more
        if ($this->protocol->isCancelled($data)) {
            return Worker::DONE;
        }

        // ended: That's all your data
        if ($this->protocol->isEnded($data)) {
            return Worker::DONE; // ready to parse
        }

        // default case: "here's a chunk of data, I'm not finished yet"
        return Worker::BUSY;
    }


    /**
     * Combine the two given parameters into a string that can be piped as a command to the worker process
     *
     * @param string $command
     * @param mixed  $arguments
     * @return string
     */
    public function composeTaskString($command, $arguments)
    {
        return $this->protocol->prepareCommand($command, $arguments);
    }


    /**
     * Separate the given parameter into an array of two elements: command and args.
     *
     * @param string $taskString
     * @throws InvalidArgumentException
     * @return array
     */
    public function decomposeTaskString($taskString)
    {
        $xpl = $this->protocol->rebuild($taskString);
        if (count($xpl) != 2) {
            throw new InvalidArgumentException("Invalid task given: '$taskString'");
        }
        return $xpl;
    }


    /**
     * Converts some data to be in a format safe for socket transmission from Worker to Server
     *
     * @param mixed $data
     * @return string
     */
    public function encodeRecord($data)
    {
        return $this->protocol->flatten($data);
    }


    /**
     * Decode the given data as received from socket transmission
     *
     * @param string $data
     * @return array
     */
    public function decodeRecords($data)
    {
        return $this->protocol->rebuild($data);
    }


    /**
     * Returns, in millis, how long the react loop should wait between dispatches from the queue
     *
     * @return int
     */
    public function getQueueDelay()
    {
        return self::QUEUE_DELAY;
    }


    /**
     * Returns the size of a queue that should be considered abnormal
     *
     * @return int
     */
    public function getWarningQueueLength()
    {
        return self::QUEUE_SIZE_WARN;
    }


    /**
     * Returns the size of a queue that should not be exceeded
     *
     * @return int
     */
    public function getMaxQueueLength()
    {
        return self::QUEUE_SIZE_MAX;
    }


    /**
     * Returns whether the Dispatcher should keep spinning, or spin down.
     * If false is returned, this method should reset any internal counter.
     *
     * @return bool
     */
    public function canDispatcherFlywheel()
    {
        if ($this->flywheelSpins-- > 0) {
            return true;
        }
        $this->flywheelSpins = self::MAX_FLYWHEEL;
        return false;
    }


    /**
     * Return the number of seconds a Worker should be idle before self-destructing
     *
     * @return int
     */
    public function getWorkerIdleTimeout()
    {
        return self::IDLE_TIMEOUT;
    }


    /**
     * Method to advise the pool that the Dispatcher is idle.
     *
     * @return void
     */
    public function dispatcherIdle()
    {
        // TODO: Implement dispatcherIdle() method.
    }


    /**
     * Method to advise the pool that the Dispatcher is engaged.
     *
     * @return void
     */
    public function dispatcherEngage()
    {
        // TODO: Implement dispatcherEngage() method.
    }


    /**
     * Returns a NEW, empty WorkerBundle.
     *
     * @return WorkerBundleInterface
     */
    public function newWorkerBundle()
    {
        return new WorkerArray($this->protocol);
    }


    /**
     * Strategically remove the workers that you want to from the given collection
     *
     * @param WorkerBundleInterface $workers
     * @return void
     */
    public function cleanup($workers)
    {
        $workers->detachWhere(Worker::ENDED);
    }


    /**
     * @param Exception       $exception
     * @param LoggerInterface $log
     * @throws PDOException
     * @return void
     */
    public function handleException($exception, $log)
    {
        $log->critical(sprintf("Exception during dispatch - %s: %s", get_class($exception), $exception->getMessage()), ['exception' => $exception]);

        // allow PDO exceptions to get out
        if ($exception instanceof PDOException) {
            throw $exception;
        }
    }


    /**
     * Returns a Queue, populated from storage if persistence is part of the strategy.
     *
     * @return SplQueue
     */
    public function buildQueue()
    {
        return new SplQueue();
    }


    /**
     * Persistent enqueue.
     *
     * @param object $identifier
     * @param string $command
     * @param array  $data
     * @return void
     */
    public function penqueue($identifier, $command, $data)
    {
        // noop: this strategy does not use persistent queues.
    }


    /**
     * Persistent dequeue.
     *
     * @param object $identifier
     * @return void
     */
    public function pdequeue($identifier)
    {
        // noop: this strategy does not use persistent queues.
    }


    /**
     * Returns the hard or soft execution timeout for a worker task (not the life of the worker itself)
     *
     * @param bool $hard
     * @return int
     */
    public function getWorkerExecutionTimeout($hard = true)
    {
        return $hard ? self::WORKER_HARD_TIMEOUT : self::WORKER_SOFT_TIMEOUT;
    }
}
