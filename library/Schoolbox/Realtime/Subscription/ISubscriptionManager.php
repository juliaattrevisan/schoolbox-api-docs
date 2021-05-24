<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Subscription;

interface ISubscriptionManager
{

    /**
     * @param int $userId
     * @return bool
     */
    public function subscribeUser($userId);

    /**
     * @param int $userId
     * @return bool
     */
    public function userHasSubscription($userId);

    /**
     * @param int $userId
     * @return ISubscription
     */
    public function getUserSubscription($userId);

    /**
     * @param int $userId
     * @return bool
     */
    public function removeSubscription($userId);
}
