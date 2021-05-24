<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew\Pooling;

use Alaress\WorkerCrew\IPC\ProtocolInterface;
use Alaress\WorkerCrew\Worker\WorkerBundleInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use SplQueue;

interface DispatchStrategyInterface
{

    // non-printable characters used for message stream control
    const STR_END_OF_TRANSMISSION = "\004";
    const STR_ACKNOWLEDGE         = "\006";
    const STR_NACKNOWLEDGE        = "\025";
    const STR_CANCEL              = "\030";
    const STR_RECORD_SEPARATOR    = "\036";
    const STR_UNIT_SEPARATOR      = "\037";


    /**
     * Get the name of the strategy currently being used
     *
     * @return string
     */
    public function getName();


    /**
     * Returns the IPC protocol that this strategy employs
     *
     * @return ProtocolInterface
     */
    public function getProtocol();


    /**
     * Returns a NEW, empty WorkerBundle.
     *
     * @return WorkerBundleInterface
     */
    public function newWorkerBundle();


    /**
     * Returns the command that will be executed each time a worker is spawned by this strategy.
     *
     * @return string
     */
    public function getCommand();


    /**
     * Returns whether or not a new command can be accepted.
     *
     * @param WorkerBundleInterface $workers
     * @return bool
     */
    public function canAccept($workers);


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
    public function getAvailableWorker($workers, $remainingTaskCount);


    /**
     * Return the list of commands that can be delegated to workers
     *
     * @return array
     */
    public function getSupportedActions();


    /**
     * Evaluate the segment of data given and return a Worker STATE based on it
     *
     * @param string $data
     * @return int
     */
    public function checkState($data);


    /**
     * Combine the two given parameters into a string that can be piped as a command to the worker process
     *
     * @param string $command
     * @param mixed  $arguments
     * @return string
     */
    public function composeTaskString($command, $arguments);


    /**
     * Separate the given parameter into an array of two elements: command and args.
     *
     * @param string $taskString
     * @throws InvalidArgumentException
     * @return array
     */
    public function decomposeTaskString($taskString);


    /**
     * Converts some data to be in a format safe for socket transmission from Worker to Server
     *
     * @param mixed $data
     * @return string
     */
    public function encodeRecord($data);


    /**
     * Decode the given data as received from socket transmission
     *
     * @param string $data
     * @return array
     */
    public function decodeRecords($data);


    /**
     * Returns, in millis, how long the react loop should wait between dispatches from the queue
     *
     * @return int
     */
    public function getQueueDelay();


    /**
     * Returns the flags that should be used for workers
     *
     * @return string
     */
    public function getFlags();


    /**
     * Returns the size of a queue that should be considered abnormal
     *
     * @return int
     */
    public function getWarningQueueLength();


    /**
     * Returns the size of a queue that should not be exceeded
     *
     * @return int
     */
    public function getMaxQueueLength();


    /**
     * Returns whether the Dispatcher should keep spinning, or spin down.
     * If false is returned, this method should reset any internal counter.
     *
     * @return bool
     */
    public function canDispatcherFlywheel();


    /**
     * Method to advise the pool that the Dispatcher is idle.
     *
     * @return void
     */
    public function dispatcherIdle();


    /**
     * Method to advise the pool that the Dispatcher is engaged.
     *
     * @return void
     */
    public function dispatcherEngage();


    /**
     * Returns the number of seconds a Worker should be idle before self-destructing
     *
     * @return int
     */
    public function getWorkerIdleTimeout();


    /**
     * Strategically removes the workers that you want to from the given collection
     *
     * @param WorkerBundleInterface $workers
     * @return void
     */
    public function cleanup($workers);


    /**
     * @param Exception       $exception
     * @param LoggerInterface $log
     * @return void
     */
    public function handleException($exception, $log);


    /**
     * Returns a Queue, populated from storage if persistence is part of the strategy.
     *
     * @return SplQueue
     */
    public function buildQueue();


    /**
     * Persistent enqueue.
     *
     * @param object $identifier
     * @param string $command
     * @param array  $data
     * @return void
     */
    public function penqueue($identifier, $command, $data);


    /**
     * Persistent dequeue.
     *
     * @param object $identifier
     * @return void
     */
    public function pdequeue($identifier);


    /**
     * Returns the hard or soft execution timeout for a worker task (not the life of the worker itself)
     *
     * @param bool $hard
     * @return int
     */
    public function getWorkerExecutionTimeout($hard = true);
}
