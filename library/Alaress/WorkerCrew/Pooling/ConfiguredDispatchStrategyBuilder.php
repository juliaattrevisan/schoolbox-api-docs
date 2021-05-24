<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew\Pooling;

class ConfiguredDispatchStrategyBuilder
{

    /** @var int */
    protected $minWorkers = DefaultDispatchStrategy::MIN_WORKERS;

    /** @var int */
    protected $maxWorkers = DefaultDispatchStrategy::MAX_WORKERS;

    /** @var int */
    protected $queueDelay = DefaultDispatchStrategy::QUEUE_DELAY;

    /** @var int */
    protected $maxFlywheel = DefaultDispatchStrategy::MAX_FLYWHEEL;

    /** @var int */
    protected $idleTimeout = DefaultDispatchStrategy::IDLE_TIMEOUT;

    /** @var int */
    protected $queueWarnSize = DefaultDispatchStrategy::QUEUE_SIZE_WARN;

    /** @var int */
    protected $queueMaxSize = DefaultDispatchStrategy::QUEUE_SIZE_MAX;

    /** @var int */
    protected $workerTimeoutSoft = DefaultDispatchStrategy::WORKER_SOFT_TIMEOUT;

    /** @var int */
    protected $workerTimeoutHard = DefaultDispatchStrategy::WORKER_HARD_TIMEOUT;


    /**
     * @param array $conf
     */
    public function __construct(array $conf = array())
    {
        foreach ($conf as $k => $v) {
            if (property_exists($this, $k)) {
                $this->$k = $v;
            }
        }
    }


    /**
     * @param string $flags
     * @return ConfiguredDispatchStrategy
     */
    public function build($flags = '')
    {
        return new ConfiguredDispatchStrategy(
            $this->minWorkers,
            $this->maxWorkers,
            $this->maxFlywheel,
            $this->queueDelay,
            $this->queueWarnSize,
            $this->queueMaxSize,
            $this->idleTimeout,
            $this->workerTimeoutSoft,
            $this->workerTimeoutHard,
            $flags
        );
    }


    /**
     * @param string $element
     * @return array|string
     */
    public function summarise($element = '')
    {
        $summary = array(
            'queue'  => sprintf("Q : n < %d <= %d, every %dms", $this->queueWarnSize, $this->queueMaxSize, $this->queueDelay),
            'worker' => sprintf("W : %d <= n <= %d", $this->minWorkers, $this->maxWorkers),
            'task'   => sprintf("T : t < %dms <= %dms", $this->workerTimeoutSoft, $this->workerTimeoutHard),
        );

        if (isset($summary[$element])) {
            return $summary[$element];
        }

        return $summary;
    }


    /**
     * @param int $idleTimeout
     * @return self
     */
    public function setIdleTimeout($idleTimeout)
    {
        $this->idleTimeout = $idleTimeout;
        return $this;
    }


    /**
     * @param int $maxFlywheel
     * @return self
     */
    public function setMaxFlywheel($maxFlywheel)
    {
        $this->maxFlywheel = $maxFlywheel;
        return $this;
    }


    /**
     * @param int $maxWorkers
     * @return self
     */
    public function setMaxWorkers($maxWorkers)
    {
        $this->maxWorkers = $maxWorkers;
        return $this;
    }


    /**
     * @param int $minWorkers
     * @return self
     */
    public function setMinWorkers($minWorkers)
    {
        $this->minWorkers = $minWorkers;
        return $this;
    }


    /**
     * @param int $queueDelay
     * @return self
     */
    public function setQueueDelay($queueDelay)
    {
        $this->queueDelay = $queueDelay;
        return $this;
    }


    /**
     * @param int $queueMaxSize
     * @return self
     */
    public function setQueueMaxSize($queueMaxSize)
    {
        $this->queueMaxSize = $queueMaxSize;
        return $this;
    }


    /**
     * @param int $queueWarnSize
     * @return self
     */
    public function setQueueWarnSize($queueWarnSize)
    {
        $this->queueWarnSize = $queueWarnSize;
        return $this;
    }


    /**
     * @param int $workerTimeoutHard
     * @return self
     */
    public function setWorkerTimeoutHard($workerTimeoutHard)
    {
        $this->workerTimeoutHard = $workerTimeoutHard;
        return $this;
    }


    /**
     * @param int $workerTimeoutSoft
     * @return self
     */
    public function setWorkerTimeoutSoft($workerTimeoutSoft)
    {
        $this->workerTimeoutSoft = $workerTimeoutSoft;
        return $this;
    }


    /**
     * @return int
     */
    public function getIdleTimeout()
    {
        return $this->idleTimeout;
    }


    /**
     * @return int
     */
    public function getMaxFlywheel()
    {
        return $this->maxFlywheel;
    }


    /**
     * @return int
     */
    public function getMaxWorkers()
    {
        return $this->maxWorkers;
    }


    /**
     * @return int
     */
    public function getMinWorkers()
    {
        return $this->minWorkers;
    }


    /**
     * @return int
     */
    public function getQueueDelay()
    {
        return $this->queueDelay;
    }


    /**
     * @return int
     */
    public function getQueueMaxSize()
    {
        return $this->queueMaxSize;
    }


    /**
     * @return int
     */
    public function getQueueWarnSize()
    {
        return $this->queueWarnSize;
    }


    /**
     * @return int
     */
    public function getWorkerTimeoutHard()
    {
        return $this->workerTimeoutHard;
    }


    /**
     * @return int
     */
    public function getWorkerTimeoutSoft()
    {
        return $this->workerTimeoutSoft;
    }
}
