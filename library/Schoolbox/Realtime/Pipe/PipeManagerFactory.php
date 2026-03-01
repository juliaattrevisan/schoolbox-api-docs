<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Pipe;

use Alaress\Redis\Async;
use Alaress\Redis\Async\Client;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use Ratchet\Session\Serialize\HandlerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\MultiInstance;
use Schoolbox\MultiInstance\ConfigRepository;
use Schoolbox\MultiInstance\ConnectorRepository;
use Schoolbox\MultiInstance\InstanceRepository;
use Schoolbox\MultiInstance\RedisAsyncClientRepository;
use Schoolbox\MultiInstance\RedisClientRepository;
use Schoolbox\Realtime\Subscription;

/**
 * Convenience class for instantiation of a `PipeManager`.
 */
class PipeManagerFactory
{
    /**
     * Take all the root dependencies, ready for composing into more complex object
     *
     * @param LoopInterface              $loop
     * @param HandlerInterface           $handler
     * @param LoggerInterface            $log
     * @param InstanceRepository         $instanceRepository
     * @param ConfigRepository           $configRepository
     * @param ConnectorRepository        $connectorRepository
     * @param RedisClientRepository      $redisClientRepository
     * @param RedisAsyncClientRepository $redisAsyncClientRepository
     * @param DispatchStrategyInterface  $strat
     * @param int                        $fdLimit
     */
    public function __construct(
        private LoopInterface $loop,
        private HandlerInterface $handler,
        private LoggerInterface $log,
        private MultiInstance\InstanceRepository $instanceRepository,
        private MultiInstance\ConfigRepository $configRepository,
        private MultiInstance\ConnectorRepository $connectorRepository,
        private MultiInstance\RedisClientRepository $redisClientRepository,
        private MultiInstance\RedisAsyncClientRepository $redisAsyncClientRepository,
        private DispatchStrategyInterface $strat,
        private int $fdLimit,
    ) {
    }


    /**
     * @return PipeManager
     */
    public function build(): PipeManager
    {
        // Requires multi-instance version
        $validator = new SessionValidator(
            $this->redisClientRepository,
            $this->handler,
            $this->connectorRepository,
            $this->configRepository,
        );

        // Requires multi-instance version
        $pipes = new PipeCollection($validator, $this->log);

        // Requires multi-instance, as Redis isn't guaranteed to be the same
        // across all instances on the node
        $subFactory = new Subscription\Redis\SubscriptionFactory(
            $this->redisClientRepository,
            $this->redisAsyncClientRepository,
            $this->log,
        );

        // Shouldn't need multi-instance?
        $subManager = new Subscription\SubscriptionManager($subFactory, $this->log);

        // Needs multi-instance, as it uses $pipes
        $pipeManager = new PipeManager($this->loop, $this->instanceRepository, $pipes, $subManager, $this->log, $this->strat, $this->fdLimit);

        // setup the listener (required before `$subFactory` is used)
        $subFactory->setListener($pipeManager);

        return $pipeManager;
    }
}
