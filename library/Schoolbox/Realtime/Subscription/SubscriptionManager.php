<?php

declare(strict_types=1);

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

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
    /** @var ISubscriptionFactory */
    private ISubscriptionFactory $factory;

    /** @var LoggerInterface */
    private LoggerInterface $log;

    /**
     * A cache of subscriptions, indexed by instance ID and then by user ID
     *
     * @var array<string, array<int, ISubscription>>
     */
    private array $subscriptions = [];

    /**
     * @param string               $instanceId
     * @param ISubscriptionFactory $factory
     * @param LoggerInterface      $log
     */
    public function __construct(string $instanceId, ISubscriptionFactory $factory, LoggerInterface $log)
    {
        $this->instanceId = $instanceId;
        $this->factory    = $factory;
        $this->log        = $log;
    }

    /**
     * @param string $instanceId
     * @param int    $userId
     * @return bool
     */
    public function subscribeUser(string $instanceId, int $userId): bool
    {
        if ($this->userHasSubscription($instanceId, $userId)) {
            // TODO consider parameterised logging
            $this->log->info("User $userId is already subscribed for instance $instanceId");
            return true;
        }

        try {
            $this->putSubscription($instanceId, $userId, $this->factory->build($instanceId, $userId));
        } catch (\RuntimeException $e) {
            return false;
        }

        return true;
    }


    /**
     * @param string $instanceId
     * @param int    $userId
     * @return bool
     */
    public function userHasSubscription(string $instanceId, int $userId): bool
    {
        return array_key_exists($userId, $this->subscriptions);
    }


    /**
     * @param string $instanceId
     * @param int    $userId
     * @return ISubscription
     */
    public function getUserSubscription(string $instanceId, int $userId): ISubscription
    {
        if (!$this->userHasSubscription($instanceId, $userId)) {
            // doesn't exist: create it
            $this->subscribeUser($instanceId, $userId);
        }

        return $this->subscriptions[$instanceId][$userId];
    }


    /**
     * @param string $instanceId
     * @param int    $userId
     * @return bool
     */
    public function removeSubscription(string $instanceId, int $userId): bool
    {
        if (!$this->userHasSubscription($instanceId, $userId)) {
            return false;
        }
        // unsetting this will call Subscription::__destruct, which will call unsubscribe()
        unset($this->subscriptions[$instanceId][$userId]);

        return true;
    }


    /**
     * @param string        $instanceId
     * @param int           $userId
     * @param ISubscription $subscription
     * @return void
     */
    private function putSubscription(string $instanceId, int $userId, ISubscription $subscription)
    {
        $this->subscriptions[$instanceId][$userId] = $subscription;
    }
}
