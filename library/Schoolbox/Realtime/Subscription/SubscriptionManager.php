<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Subscription;

use Psr\Log\LoggerInterface;

/**
 * Standard manager of subscriptions. Strictly uses interfaces only and provides no functionality
 * beyond the ISubscriptionManager that it implements.
 *
 * @package Schoolbox\Realtime\Subscription
 */
class SubscriptionManager implements ISubscriptionManager
{
    /**
     * A cache of subscriptions, indexed by instance ID and then by user ID
     *
     * @var array<string, array<int, ISubscription>>
     */
    private array $subscriptions = [];

    /**
     * Constructor
     *
     * @param ISubscriptionFactory $factory Subscription factory
     * @param LoggerInterface      $log     Logger
     */
    public function __construct(private ISubscriptionFactory $factory, private LoggerInterface $log)
    {
    }

    /**
     * @inheritdoc
     */
    public function subscribeUser(string $instanceId, int $userId): bool
    {
        $this->log->debug("Subscribing instance:user $instanceId:$userId");
        if ($this->userHasSubscription($instanceId, $userId)) {
            // TODO consider parameterised logging
            $this->log->info("Instance:user $instanceId:$userId is already subscribed");
            return true;
        }

        try {
            $this->putSubscription($instanceId, $userId, $this->factory->build($instanceId, $userId));
            $this->log->debug("Subscribed instance:user $instanceId:$userId");
        } catch (\RuntimeException $e) {
            $this->log->warning("Failed to subscribe instance:user $instanceId:$userId: {$e->getMessage()}");
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function userHasSubscription(string $instanceId, int $userId): bool
    {
        return isset($this->subscriptions[$instanceId][$userId]);
    }

    /**
     * @inheritdoc
     */
    public function getUserSubscription(string $instanceId, int $userId): ISubscription
    {
        $this->log->debug("Retrieving subscription for instance:user $instanceId:$userId");
        if (!$this->userHasSubscription($instanceId, $userId)) {
            // doesn't exist: create it
            $this->subscribeUser($instanceId, $userId);
        }

        return $this->subscriptions[$instanceId][$userId];
    }

    /**
     * @inheritdoc
     */
    public function removeSubscription(string $instanceId, int $userId): bool
    {
        $this->log->debug("Unsubscribing instance:user $instanceId:$userId");
        if (!$this->userHasSubscription($instanceId, $userId)) {
            $this->log->info("Instance:user $instanceId:$userId is not subscribed");
            return false;
        }
        // unsetting this will call Subscription::__destruct, which will call unsubscribe()
        unset($this->subscriptions[$instanceId][$userId]);
        $this->log->debug("Unsubscribed instance:user $instanceId:$userId");

        // Clean up empty instance array to prevent memory accumulation
        if (empty($this->subscriptions[$instanceId])) {
            unset($this->subscriptions[$instanceId]);
        }

        return true;
    }

    /**
     * Cache this subscription.
     *
     * @param string        $instanceId
     * @param int           $userId
     * @param ISubscription $subscription
     * @return void
     */
    private function putSubscription(string $instanceId, int $userId, ISubscription $subscription): void
    {
        $this->subscriptions[$instanceId][$userId] = $subscription;
    }
}
