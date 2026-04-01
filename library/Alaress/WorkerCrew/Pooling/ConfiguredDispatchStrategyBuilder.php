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
    protected $minWorkers = DispatchStrategy::MIN_WORKERS;

    /** @var int */
    protected $maxWorkers = DispatchStrategy::MAX_WORKERS;

    /** @var int */
    protected $queueDelay = DispatchStrategy::QUEUE_DELAY;

    /** @var int */
    protected $maxFlywheel = DispatchStrategy::MAX_FLYWHEEL;

    /** @var int */
    protected $idleTimeout = DispatchStrategy::IDLE_TIMEOUT;

    /** @var int */
    protected $queueWarnSize = DispatchStrategy::QUEUE_SIZE_WARN;

    /** @var int */
    protected $queueMaxSize = DispatchStrategy::QUEUE_SIZE_MAX;

    /** @var int */
    protected $workerTimeoutSoft = DispatchStrategy::WORKER_SOFT_TIMEOUT;

    /** @var int */
    protected $workerTimeoutHard = DispatchStrategy::WORKER_HARD_TIMEOUT;

    /** @var int */
    protected $maxRequestsPerWorker = DispatchStrategy::MAX_REQUESTS_PER_WORKER;

    /** @var int */
    protected $workerWriteTimeout = DefaultDispatchStrategy::WORKER_WRITE_TIMEOUT;

    /** @var int */
    protected $metricInterval = DefaultDispatchStrategy::METRIC_INTERVAL;

    /** @var int */
    protected $maxFutureTriggerTime = DispatchStrategy::MAX_FUTURE_TRIGGER_TIME;

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
     * Build a dispatch strategy as per the configuration.
     *
     * @param string $flags
     * @return DispatchStrategyInterface
     */
    public function build(string $flags = ''): DispatchStrategyInterface
    {
        return new DispatchStrategy(
            $this->minWorkers,
            $this->maxWorkers,
            $this->maxFlywheel,
            $this->queueDelay,
            $this->queueWarnSize,
            $this->queueMaxSize,
            $this->idleTimeout,
            $this->workerTimeoutSoft,
            $this->workerTimeoutHard,
            $flags,
            $this->maxRequestsPerWorker,
            $this->workerWriteTimeout,
            $this->metricInterval,
            $this->maxFutureTriggerTime,
        );
    }

    /**
     * Return a summary of this dispatch strategy builder, for output in logs.
     *
     * @return array
     */
    public function summarise(): array
    {
        $maxRequestsPerWorkerOutput = $this->maxRequestsPerWorker > 0
            ? sprintf('max %d', $this->maxRequestsPerWorker)
            : 'no max';
        return [
            'metrics' => sprintf("Metrics every %dms", $this->metricInterval),
            'queue'   => sprintf(
                "Queue:    %s warn, %s max, %dms check interval",
                $this->queueWarnSize ?: 'inf',
                $this->queueMaxSize ?: 'inf',
                $this->queueDelay,
            ),
            'worker'  => sprintf(
                "Workers:  %d min, %d max, %s requests/worker",
                $this->minWorkers,
                $this->maxWorkers,
                $maxRequestsPerWorkerOutput,
            ),
            'task'    => sprintf(
                "Timeouts: %dms write accept, %dms execution (soft), %dms execution (hard)",
                $this->workerWriteTimeout,
                $this->workerTimeoutSoft,
                $this->workerTimeoutHard,
            ),
        ];
    }
}
