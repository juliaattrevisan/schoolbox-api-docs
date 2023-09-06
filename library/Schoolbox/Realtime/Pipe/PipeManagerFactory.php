<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Pipe;

use Alaress\Database\Connector\IConnector;
use Alaress\Redis\Async;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use Ratchet\Session\Serialize\HandlerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\MultiInstance\ConfigRepository;
use Schoolbox\MultiInstance\ConnectorRepository;
use Schoolbox\Realtime\Subscription;

/**
 * Convenience class for instantiation of the Pipe Manager
 *
 * @package Schoolbox\Realtime\Pipe
 */
class PipeManagerFactory
{
    /**
     * Take all the root dependencies, ready for composing into more complex object
     *
     * @param LoopInterface             $loop
     * @param HandlerInterface          $handler
     * @param LoggerInterface           $log
     * @param ClientInterface           $redis
     * @param Async\Client              $asyncRedis
     * @param ConfigRepository          $configRepository
     * @param ConnectorRepository       $connectorRepository
     * @param DispatchStrategyInterface $strat
     * @param int                       $fdLimit
     */
    public function __construct(
        private LoopInterface $loop,
        private HandlerInterface $handler,
        private LoggerInterface $log,
        private ClientInterface $redis,
        private Async\Client $asyncRedis,
        private ConfigRepository $configRepository,
        private ConnectorRepository $connectorRepository,
        private DispatchStrategyInterface $strat,
        private int $fdLimit,
    ) {
    }


    /**
     * @return PipeManager
     */
    public function build()
    {
        // Requires multi-instance version
        $validator   = new SessionValidator(
            $this->redis,
            $this->handler,
            $this->connectorRepository,
            $this->configRepository,
        );

        // Requires multi-instance version
        $pipes       = new PipeCollection($validator, $this->log);

        // Shouldn't need multi-instance?
        $subFactory  = new Subscription\Redis\SubscriptionFactory($this->redis, $this->asyncRedis);

        // Shouldn't need multi-instance?
        $subManager  = new Subscription\SubscriptionManager($subFactory, $this->log);

        // Needs multi-instance, as it uses $pipes
        $pipeManager = new PipeManager($this->loop, $pipes, $subManager, $this->log, $this->strat, $this->fdLimit);

        // setup the listener
        $subFactory->setListener($pipeManager);

        return $pipeManager;
    }
}
