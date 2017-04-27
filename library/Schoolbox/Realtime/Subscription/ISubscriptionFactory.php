<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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