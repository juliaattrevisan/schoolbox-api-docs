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
use Throwable;

interface DispatchStrategyInterface
{
    // non-printable characters used for message stream control
    public const string STR_END_OF_TRANSMISSION = "\004";
    public const string STR_ACKNOWLEDGE         = "\006";
    public const string STR_NACKNOWLEDGE        = "\025";
    public const string STR_CANCEL              = "\030";
    public const string STR_RECORD_SEPARATOR    = "\036";
    public const string STR_UNIT_SEPARATOR      = "\037";


    /**
     * Returns the IPC protocol that this strategy employs
     *
     * @return ProtocolInterface
     */
    public function getProtocol(): ProtocolInterface;


    /**
     * Returns a NEW, empty WorkerBundle.
     *
     * @return WorkerBundleInterface
     */
    public function newWorkerBundle(): WorkerBundleInterface;


    /**
     * Returns the command that will be executed each time a worker is spawned by this strategy.
     *
     * @return string
     */
    public function getCommand(): string;


    /**
     * Returns whether or not a new command can be accepted.
     *
     * @param WorkerBundleInterface $workers
     * @return bool
     */
    public function canAccept(WorkerBundleInterface $workers): bool;


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
    public function getAvailableWorker(WorkerBundleInterface $workers, int $remainingTaskCount): int;


    /**
     * Return the list of commands that can be delegated to workers
     *
     * @return array
     */
    public function getSupportedActions(): array;


    /**
     * Evaluate the segment of data given and return a Worker STATE based on it
     *
     * @param string $data
     * @return int
     */
    public function checkState(string $data): int;


    /**
     * Combine the two given parameters into a string that can be piped as a command to the worker process
     *
     * @param string $command
     * @param mixed  $arguments
     * @return string
     */
    public function composeTaskString(string $command, mixed $arguments): string;


    /**
     * Separate the given parameter into an array of two elements: command and args.
     *
     * @param string $taskString
     * @return array
     * @throws InvalidArgumentException
     */
    public function decomposeTaskString(string $taskString): array;


    /**
     * Converts some data to be in a format safe for socket transmission from Worker to Server
     *
     * @param mixed $data
     * @return string
     */
    public function encodeRecord(mixed $data): string;


    /**
     * Decode the given data as received from socket transmission
     *
     * @param string $data
     * @return array
     */
    public function decodeRecords(string $data): array;


    /**
     * Returns, in millis, how long the react loop should wait between dispatches from the queue
     *
     * @return int
     */
    public function getQueueDelay(): int;


    /**
     * Returns the flags that should be used for workers
     *
     * @return string
     */
    public function getFlags(): string;


    /**
     * Returns the size of a queue that should be considered abnormal
     *
     * @return int
     */
    public function getWarningQueueLength(): int;


    /**
     * Returns the size of a queue that should not be exceeded
     *
     * @return int
     */
    public function getMaxQueueLength(): int;


    /**
     * Returns whether the Dispatcher should keep spinning, or spin down.
     * If false is returned, this method should reset any internal counter.
     *
     * @return bool
     */
    public function canDispatcherFlywheel(): bool;


    /**
     * Returns the number of milliseconds a Worker should be idle before self-destructing
     *
     * @return int
     */
    public function getWorkerIdleTimeout(): int;


    /**
     * Strategically removes the workers that you want to from the given collection
     *
     * @param WorkerBundleInterface $workers
     * @return void
     */
    public function cleanup(WorkerBundleInterface $workers): void;


    /**
     * @param Throwable       $exception
     * @param LoggerInterface $log
     * @return void
     */
    public function handleException(Throwable $exception, LoggerInterface $log): void;


    /**
     * Returns the hard or soft execution timeout for a worker task (not the life of the worker itself),
     * in milliseconds.
     *
     * @param bool $hard
     * @return int
     */
    public function getWorkerExecutionTimeout(bool $hard = true): int;


    /**
     * Returns the maximum number of requests a worker should process before being retired (0 = unlimited)
     *
     * @return int
     */
    public function getMaxRequestsPerWorker(): int;


    /**
     * Returns the maximum time in milliseconds to wait for a worker to accept a write before killing it
     *
     * @return int
     */
    public function getWorkerWriteTimeout(): int;


    /**
     * The interval (in seconds) at which daemon metrics should be reported.
     *
     * @return int
     */
    public function getMetricInterval(): int;

    /**
     * Return the max number of milliseconds in the future for which the scheduler which uses this dispatcher should
     * queue future triggers.
     *
     * If zero, then set no maximum time: always queue future triggers.
     *
     * @return int
     */
    public function getMaxFutureTriggerTime(): int;
}
