<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew\Pooling;

use Alaress\WorkerCrew\IPC\AlaressIPCProtocol;
use Alaress\WorkerCrew\Worker\Worker;
use Alaress\WorkerCrew\Worker\WorkerBundleInterface;
use OutOfBoundsException;

class ConfiguredDispatchStrategy extends DispatchStrategy
{
    protected $flywheelSpins;

    protected $minWorkers;
    protected $maxWorkers;
    protected $queueDelay;
    protected $maxFlywheel;
    protected $idleTimeout;
    protected $queueWarnSize;
    protected $queueMaxSize;
    protected $workerTimeoutSoft;
    protected $workerTimeoutHard;
    protected $workerWriteTimeout;
    protected $maxRequestsPerWorker;
    protected $metricInterval;
    protected $maxFutureTriggerTime;


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
     * @param int    $workerWriteTimeout
     * @param int    $maxFutureTriggerTime
     */
    public function __construct(
        $minWorkers = DispatchStrategy::MIN_WORKERS,
        $maxWorkers = DispatchStrategy::MAX_WORKERS,
        $maxFlywheel = DispatchStrategy::MAX_FLYWHEEL,
        $queueDelay = DispatchStrategy::QUEUE_DELAY,
        $queueWarnSize = DispatchStrategy::QUEUE_SIZE_WARN,
        $queueMaxSize = DispatchStrategy::QUEUE_SIZE_MAX,
        $idleTimeout = DispatchStrategy::IDLE_TIMEOUT,
        $workerTimeoutSoft = DispatchStrategy::WORKER_SOFT_TIMEOUT,
        $workerTimeoutHard = DispatchStrategy::WORKER_HARD_TIMEOUT,
        $flags = '',
        $maxRequestsPerWorker = DispatchStrategy::MAX_REQUESTS_PER_WORKER,
        $workerWriteTimeout = DefaultDispatchStrategy::WORKER_WRITE_TIMEOUT,
        $metricInterval = DefaultDispatchStrategy::METRIC_INTERVAL,
        $maxFutureTriggerTime = DispatchStrategy::MAX_FUTURE_TRIGGER_TIME,
    ) {
        parent::__construct($flags);
        $this->flywheelSpins        = $maxFlywheel;
        $this->minWorkers           = $minWorkers;
        $this->maxWorkers           = $maxWorkers;
        $this->queueDelay           = $queueDelay;
        $this->queueWarnSize        = $queueWarnSize;
        $this->queueMaxSize         = $queueMaxSize;
        $this->maxFlywheel          = $maxFlywheel;
        $this->idleTimeout          = $idleTimeout;
        $this->workerTimeoutSoft    = $workerTimeoutSoft;
        $this->workerTimeoutHard    = $workerTimeoutHard;
        $this->workerWriteTimeout   = $workerWriteTimeout;
        $this->maxRequestsPerWorker = $maxRequestsPerWorker;
        $this->metricInterval       = $metricInterval;
        $this->maxFutureTriggerTime = $maxFutureTriggerTime;
        $this->protocol             = new AlaressIPCProtocol();
    }


    /**
     * Get the name of the strategy currently being used
     *
     * @return string
     */
    public function getName()
    {
        return 'Configured';
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
        if ($workers->countWhere(~(Worker::ENDED | Worker::ERROR)) < $this->maxWorkers) {
            return 0;
        }

        return -1;
    }


    /**
     * Returns, in millis, how long the react loop should wait between dispatches from the queue
     *
     * @return int
     */
    public function getQueueDelay()
    {
        return $this->queueDelay;
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
        $this->flywheelSpins = $this->maxFlywheel;
        return false;
    }


    /**
     * Return the number of seconds a Worker should be idle before self-destructing
     *
     * @return int
     */
    public function getWorkerIdleTimeout()
    {
        return $this->idleTimeout;
    }


    /**
     * @return int
     */
    public function getWarningQueueLength()
    {
        return $this->queueWarnSize;
    }


    /**
     * @return int
     */
    public function getMaxQueueLength()
    {
        return $this->queueMaxSize;
    }


    public function getWorkerExecutionTimeout($hard = true)
    {
        return $hard ? $this->workerTimeoutHard : $this->workerTimeoutSoft;
    }


    /**
     * @return int
     */
    public function getMaxRequestsPerWorker(): int
    {
        return $this->maxRequestsPerWorker;
    }


    /**
     * @return int
     */
    public function getWorkerWriteTimeout(): int
    {
        return $this->workerWriteTimeout;
    }


    /**
     * @return int
     */
    public function getMetricInterval(): int
    {
        return $this->metricInterval;
    }


    /**
     * @return int
     */
    public function getMaxFutureTriggerTime(): int
    {
        return $this->maxFutureTriggerTime;
    }
}
