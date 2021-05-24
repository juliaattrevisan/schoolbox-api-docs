<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Subscription;

interface ISubscriptionFactory
{

    /**
     * @param string $instanceId
     * @param int    $userId
     * @return ISubscription
     */
    public function build($instanceId, $userId);
}
