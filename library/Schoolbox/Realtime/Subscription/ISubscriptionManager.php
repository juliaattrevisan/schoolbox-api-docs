<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Subscription;

/**
 * Interface for subscribing and unsubscribing users from some sort of queue.
 */
interface ISubscriptionManager
{
    /**
     * Subscribes a user identified by `$userId` within instance `$instanceId`
     * to the queue.
     *
     * @param string $instanceId
     * @param int    $userId
     * @return bool Did the subscription succeed?
     */
    public function subscribeUser(string $instanceId, int $userId): bool;

    /**
     * Does the user identified by `$userId` within instance `$instanceId` have
     * a subscription already?
     *
     * @param string $instanceId
     * @param int    $userId
     * @return bool
     */
    public function userHasSubscription(string $instanceId, int $userId): bool;

    /**
     * Gets the subscription for the given user in the given instance.
     *
     * @param string $instanceId
     * @param int    $userId
     * @return ISubscription
     */
    public function getUserSubscription(string $instanceId, int $userId): ISubscription;

    /**
     * Unsubscribes a user identified by `$userId` within instance `$instanceId`
     * from the queue.
     *
     * @param string $instanceId
     * @param int    $userId
     * @return bool Did the unsubscription succeed?
     */
    public function removeSubscription(string $instanceId, int $userId): bool;
}
