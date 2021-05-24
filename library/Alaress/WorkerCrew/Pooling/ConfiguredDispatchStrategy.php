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

class ConfiguredDispatchStrategy extends DefaultDispatchStrategy
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
     */
    public function __construct($minWorkers, $maxWorkers, $maxFlywheel, $queueDelay, $queueWarnSize, $queueMaxSize, $idleTimeout, $workerTimeoutSoft, $workerTimeoutHard, $flags = '')
    {
        parent::__construct($flags);
        $this->flywheelSpins = $maxFlywheel;
        $this->minWorkers    = $minWorkers;
        $this->maxWorkers    = $maxWorkers;
        $this->queueDelay    = $queueDelay;
        $this->queueWarnSize = $queueWarnSize;
        $this->queueMaxSize  = $queueMaxSize;
        $this->maxFlywheel   = $maxFlywheel;
        $this->idleTimeout   = $idleTimeout;
        $this->workerTimeoutSoft = $workerTimeoutSoft;
        $this->workerTimeoutHard = $workerTimeoutHard;
        $this->protocol      = new AlaressIPCProtocol();
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
        if ($workers->countWhere(~(Worker::ENDED & Worker::ERROR)) < $this->maxWorkers) {
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
}
