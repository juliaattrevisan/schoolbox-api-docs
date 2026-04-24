<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\WorkerCrew\Pooling;

/**
 * Builder for dispatch strategies, as configured by an array read from a
 * config file.
 */
class ConfiguredDispatchStrategyBuilder
{
    /**
     * Minimum workers which may be alive at any one time.
     *
     * @see DispatchStrategyInterface::getAvailableWorker()
     */
    protected int $minWorkers = DispatchStrategy::MIN_WORKERS;

    /**
     * Maximum workers which may be alive at any one time.
     *
     * @see DispatchStrategyInterface::getAvailableWorker()
     */
    protected int $maxWorkers = DispatchStrategy::MAX_WORKERS;

    /**
     * @see DispatchStrategyInterface::getQueueDelay()
     */
    protected int $queueDelay = DispatchStrategy::QUEUE_DELAY;

    /**
     * How many times may the dispatcher run with an empty queue, before being
     * spun down (and restarted once the queue is no longer empty)?
     *
     * @see DispatchStrategyInterface::canDispatcherFlywheel()
     */
    protected int $maxFlywheel = DispatchStrategy::MAX_FLYWHEEL;

    /**
     * @see DispatchStrategyInterface::getWorkerIdleTimeout()
     */
    protected int $idleTimeout = DispatchStrategy::IDLE_TIMEOUT;

    /**
     * @see DispatchStrategyInterface::getWarningQueueLength()
     */
    protected int $queueWarnSize = DispatchStrategy::QUEUE_SIZE_WARN;

    /**
     * @see DispatchStrategyInterface::getMaxQueueLength()
     */
    protected int $queueMaxSize = DispatchStrategy::QUEUE_SIZE_MAX;

    /**
     * @see DispatchStrategyInterface::getWorkerExecutionTimeout(hard: false)
     */
    protected int $workerTimeoutSoft = DispatchStrategy::WORKER_SOFT_TIMEOUT;

    /**
     * @see DispatchStrategyInterface::getWorkerExecutionTimeout(hard: true)
     */
    protected int $workerTimeoutHard = DispatchStrategy::WORKER_HARD_TIMEOUT;

    /**
     * @see DispatchStrategyInterface::getMaxRequestsPerWorker()
     */
    protected int $maxRequestsPerWorker = DispatchStrategy::MAX_REQUESTS_PER_WORKER;

    /**
     * @see DispatchStrategyInterface::getWorkerWriteTimeout()
     */
    protected int $workerWriteTimeout = DispatchStrategy::WORKER_WRITE_TIMEOUT;

    /**
     * @see DispatchStrategyInterface::getMetricInterval()
     */
    protected int $metricInterval = DispatchStrategy::METRIC_INTERVAL;

    /**
     * @see DispatchStrategyInterface::getMaxFutureTriggerTime()
     */
    protected int $maxFutureTriggerTime = DispatchStrategy::MAX_FUTURE_TRIGGER_TIME;

    /**
     * Constructor
     *
     * @param array<string, int|numeric-string> $conf Any properties which may be set in the dispatch strategy.
     *
     * @see ConfigurationReader for the format of `$conf`
     */
    public function __construct(array $conf = [])
    {
        foreach ($conf as $k => $v) {
            if (property_exists($this, $k)) {
                // It just so happens that all properties are ints
                $this->$k = intval($v);
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
            $this->maxFutureTriggerTime,
            $this->workerWriteTimeout,
            $this->metricInterval,
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
