<?php

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

    /** @var string */
    private $instanceId;

    /** @var ISubscriptionFactory */
    private $factory;

    /** @var LoggerInterface */
    private $log;

    /**
     * A cache of subscriptions, indexed by user ID
     *
     * @var ISubscription[]
     */
    private $subscriptions = [];

    /**
     * @param string               $instanceId
     * @param ISubscriptionFactory $factory
     * @param LoggerInterface      $log
     */
    public function __construct($instanceId, ISubscriptionFactory $factory, LoggerInterface $log)
    {
        $this->instanceId = $instanceId;
        $this->factory    = $factory;
        $this->log        = $log;
    }

    /**
     * @param int $userId
     * @return bool
     */
    public function subscribeUser($userId)
    {
        if ($this->userHasSubscription($userId)) {
            $this->log->info("User $userId is already subscribed");
            return true;
        }

        try {
            $this->putSubscription($userId, $this->factory->build($this->instanceId, $userId));
        } catch (\RuntimeException $e) {
            return false;
        }

        return true;
    }


    /**
     * @param int $userId
     * @return bool
     */
    public function userHasSubscription($userId)
    {
        return array_key_exists($userId, $this->subscriptions);
    }


    /**
     * @param int $userId
     * @return ISubscription
     */
    public function getUserSubscription($userId)
    {
        if (!$this->userHasSubscription($userId)) {
            // doesn't exist: create it
            $this->subscribeUser($userId);
        }

        return $this->subscriptions[$userId];
    }


    /**
     * @param int $userId
     * @return bool
     */
    public function removeSubscription($userId)
    {
        if (!$this->userHasSubscription($userId)) {
            return false;
        }
        // unsetting this will call Subscription::__destruct, which will call unsubscribe()
        unset($this->subscriptions[$userId]);

        return true;
    }


    /**
     * @param int           $userId
     * @param ISubscription $subscription
     * @return void
     */
    private function putSubscription($userId, ISubscription $subscription)
    {
        $this->subscriptions[$userId] = $subscription;
    }
}
