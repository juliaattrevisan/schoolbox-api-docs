<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\WorkerCrew\Pooling;

use Alaress\WorkerCrew\IPC\AlaressIPCProtocol;
use Alaress\WorkerCrew\IPC\ProtocolInterface;
use Alaress\WorkerCrew\Worker\Worker;
use Alaress\WorkerCrew\Worker\WorkerArray;
use Alaress\WorkerCrew\Worker\WorkerBundleInterface;
use InvalidArgumentException;
use OutOfBoundsException;
use PDOException;
use Psr\Log\LoggerInterface;
use Throwable;

class DispatchStrategy implements DispatchStrategyInterface
{
    public const int MIN_WORKERS  = 1;
    public const int MAX_WORKERS  = 6;
    public const int MAX_FLYWHEEL = 0;

    public const int QUEUE_DELAY     = 1;
    public const int QUEUE_SIZE_WARN = PHP_INT_MAX;
    public const int QUEUE_SIZE_MAX  = PHP_INT_MAX;

    public const int IDLE_TIMEOUT = 60000; // 1 minute

    public const int MAX_REQUESTS_PER_WORKER = 0; // 0 = unlimited

    public const int MAX_FUTURE_TRIGGER_TIME = 0; // 0 = unlimited

    public const int WORKER_SOFT_TIMEOUT = 300000; // 5 minutes
    public const int WORKER_HARD_TIMEOUT = 1200000; // 20 minutes

    public const string TASK_FETCH     = 'fetch';
    public const string TASK_PUBLISH   = 'publish';
    public const string TASK_DIGEST    = 'digest';
    public const string TASK_TIMETABLE = 'timetable';
    public const string TASK_MOBILE    = 'mobile';

    protected int $flywheelSpins;

    /**
     * The protocol used for communication between the worker pool process,
     * and its workers.
     */
    protected AlaressIPCProtocol $protocol;

    /**
     * @param int    $minWorkers
     * @param int    $maxWorkers
     * @param int    $maxFlywheel
     * @param int    $queueDelay
     * @param int    $queueWarnSize
     * @param int    $queueMaxSize
     * @param int    $idleTimeout
     * @param int    $workerTimeoutSoft
     * @param int    $workerTimeoutHard
     * @param string $flags
     * @param int    $maxRequestsPerWorker
     * @param int    $maxFutureTriggerTime
     */
    public function __construct(
        private int $minWorkers = DispatchStrategy::MIN_WORKERS,
        private int $maxWorkers = DispatchStrategy::MAX_WORKERS,
        private int $maxFlywheel = DispatchStrategy::MAX_FLYWHEEL,
        private int $queueDelay = DispatchStrategy::QUEUE_DELAY,
        private int $queueWarnSize = DispatchStrategy::QUEUE_SIZE_WARN,
        private int $queueMaxSize = DispatchStrategy::QUEUE_SIZE_MAX,
        private int $idleTimeout = DispatchStrategy::IDLE_TIMEOUT,
        private int $workerTimeoutSoft = DispatchStrategy::WORKER_SOFT_TIMEOUT,
        private int $workerTimeoutHard = DispatchStrategy::WORKER_HARD_TIMEOUT,
        private string $flags = '',
        private int $maxRequestsPerWorker = DispatchStrategy::MAX_REQUESTS_PER_WORKER,
        private int $maxFutureTriggerTime = DispatchStrategy::MAX_FUTURE_TRIGGER_TIME,
    ) {
        $this->flywheelSpins = $maxFlywheel;
        $this->protocol      = new AlaressIPCProtocol();
    }


    /**
     * Returns the IPC protocol that this strategy employs
     *
     * @return ProtocolInterface
     */
    public function getProtocol(): ProtocolInterface
    {
        return $this->protocol;
    }


    /**
     * Returns the command that will be executed each time a worker is spawned by this strategy.
     *
     * @return string
     */
    public function getCommand(): string
    {
        return SITE_root . "/tools/socket/worker.php";
    }


    /**
     * Returns whether or not a new command can be accepted.
     *
     * @param WorkerBundleInterface $workers
     * @return bool
     */
    public function canAccept(WorkerBundleInterface $workers): bool
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
    public function getAvailableWorker(WorkerBundleInterface $workers, int $remainingTaskCount): int
    {
        // if we haven't got the min number of workers, favour starting a new one
        if ($workers->count() < $this->minWorkers) {
            return 0;
        }

        // if a worker is free, yes we can accept
        try {
            return $workers->getPidWhere(Worker::READY);
        } catch (OutOfBoundsException $e) {
            // ok, so there's none of those
        }

        // if number of workers THAT ARE NOT ENDED OR ERRORED is below the cap, spawn a new one
        if ($workers->countWhere(~(Worker::ENDED & Worker::ERROR)) < $this->maxWorkers) {
            return 0;
        }

        return -1;
    }


    /**
     * Return the list of commands that can be delegated to workers
     *
     * @return array
     */
    public function getSupportedActions(): array
    {
        return [self::TASK_FETCH, self::TASK_PUBLISH, self::TASK_DIGEST, self::TASK_TIMETABLE];
    }


    /**
     * Returns the flags that should be used for workers
     *
     * @return string
     */
    public function getFlags(): string
    {
        return $this->flags;
    }


    /**
     * Evaluate the segment of data given and return a Worker STATE based on it
     *
     * @param string $data
     * @return int
     */
    public function checkState(string $data): int
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
    public function composeTaskString(string $command, mixed $arguments): string
    {
        return $this->protocol->prepareCommand($command, $arguments);
    }


    /**
     * Separate the given parameter into an array of two elements: command and args.
     *
     * @param string $taskString
     * @return array
     *@throws InvalidArgumentException
     */
    public function decomposeTaskString(string $taskString): array
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
    public function encodeRecord(mixed $data): string
    {
        return $this->protocol->flatten($data);
    }


    /**
     * Decode the given data as received from socket transmission
     *
     * @param string $data
     * @return array
     */
    public function decodeRecords(string $data): array
    {
        return $this->protocol->rebuild($data);
    }


    /**
     * Returns, in millis, how long the react loop should wait between dispatches from the queue
     *
     * @return int
     */
    public function getQueueDelay(): int
    {
        return $this->queueDelay;
    }


    /**
     * Returns the size of a queue that should be considered abnormal
     *
     * @return int
     */
    public function getWarningQueueLength(): int
    {
        return $this->queueWarnSize;
    }


    /**
     * Returns the size of a queue that should not be exceeded
     *
     * @return int
     */
    public function getMaxQueueLength(): int
    {
        return $this->queueMaxSize;
    }


    /**
     * Returns whether the Dispatcher should keep spinning, or spin down.
     * If false is returned, this method should reset any internal counter.
     *
     * @return bool
     */
    public function canDispatcherFlywheel(): bool
    {
        if ($this->flywheelSpins-- > 0) {
            return true;
        }
        $this->flywheelSpins = $this->maxFlywheel;
        return false;
    }


    /**
     * Return the number of seconds a Worker should be idle before self-destructing
     *
     * @return int
     */
    public function getWorkerIdleTimeout(): int
    {
        return $this->idleTimeout;
    }


    /**
     * Returns a NEW, empty WorkerBundle.
     *
     * @return WorkerBundleInterface
     */
    public function newWorkerBundle(): WorkerBundleInterface
    {
        return new WorkerArray($this->protocol);
    }


    /**
     * Strategically remove the workers that you want to from the given collection
     *
     * @param WorkerBundleInterface $workers
     * @return void
     */
    public function cleanup(WorkerBundleInterface $workers): void
    {
        $workers->detachWhere(Worker::ENDED);
    }


    /**
     * @param Throwable      $exception
     * @param LoggerInterface $log
     * @return void
     * @throws PDOException
     */
    public function handleException(Throwable $exception, LoggerInterface $log): void
    {
        $log->critical(sprintf("Exception during dispatch - %s: %s", get_class($exception), $exception->getMessage()), ['exception' => $exception]);

        // allow PDO exceptions to get out
        if ($exception instanceof PDOException) {
            throw $exception;
        }
    }


    /**
     * Returns the hard or soft execution timeout for a worker task (not the life of the worker itself)
     *
     * @param bool $hard
     * @return int
     */
    public function getWorkerExecutionTimeout(bool $hard = true): int
    {
        return $hard ? $this->workerTimeoutHard : $this->workerTimeoutSoft;
    }


    /**
     * Returns the maximum number of requests a worker should process before being retired (0 = unlimited)
     *
     * @return int
     */
    public function getMaxRequestsPerWorker(): int
    {
        return $this->maxRequestsPerWorker;
    }


    /**
     * @return int
     */
    public function getMaxFutureTriggerTime(): int
    {
        return $this->maxFutureTriggerTime;
    }
}
