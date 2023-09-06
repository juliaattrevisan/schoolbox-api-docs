<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Subscription;

/**
 * Interface for building subscriptions.
 */
interface ISubscriptionFactory
{
    /**
     * Build a subscription.
     *
     * @param string $instanceId The instance ID within which the user exists.
     * @param int    $userId     The ID of the subscription's user.
     * @return ISubscription
     */
    public function build(string $instanceId, int $userId): ISubscription;
}
