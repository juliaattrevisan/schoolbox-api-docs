<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Subscription\Redis;

use Predis\ClientException;
use Psr\Log\LoggerInterface;
use Schoolbox\MultiInstance\RedisAsyncClientRepository;
use Schoolbox\MultiInstance\RedisClientRepository;
use Schoolbox\Realtime\Subscription\ISubscription;
use Schoolbox\Realtime\Subscription\ISubscriptionFactory;
use Alaress\Redis\Async;

/**
 * Factory for creating subscription objects.
 */
class SubscriptionFactory implements ISubscriptionFactory
{
    /**
     * The async event listener which subscriptions this factory creates will
     * use.
     *
     * Must be set before `build()` is called.
     */
    private Async\EventListener $listener;

    /**
     * Constructor
     *
     * @param RedisClientRepository      $redisClientRepository
     * @param RedisAsyncClientRepository $redisAsyncClientRepository
     * @param LoggerInterface            $log
     */
    public function __construct(
        private RedisClientRepository $redisClientRepository,
        private RedisAsyncClientRepository $redisAsyncClientRepository,
        private LoggerInterface $log,
    ) {
    }

    /**
     * @param Async\EventListener $listener
     * @return $this
     */
    public function setListener(Async\EventListener $listener): self
    {
        $this->listener = $listener;
        return $this;
    }

    /**
     * Build a subscription.
     *
     * `$this->setListener` must be called first, as the subscription this
     * creates requires a listener.
     *
     * @param string $instanceId
     * @param int    $userId
     * @return ISubscription
     * @throws ClientException
     */
    public function build(string $instanceId, int $userId): ISubscription
    {
        $redisClient = $this->redisClientRepository->fetchForInstance($instanceId);
        return new Subscription(
            $instanceId,
            $userId,
            $redisClient,
            $this->redisAsyncClientRepository->fetchForInstance($instanceId),
            $this->listener,
            new UnreadRetriever($redisClient),
            $this->log,
        );
    }
}
