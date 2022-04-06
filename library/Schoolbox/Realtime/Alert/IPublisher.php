<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Alert;

use Alaress\Async\Notification\Instance\Instance;

/**
 * Interface for message publishers.
 */
interface IPublisher
{
    /**
     * Publishes a push message to the given user.
     *
     * @param int      $userId
     * @param Instance $instance
     * @return int the number of recipients successfully pushed to
     */
    public function pushUser(int $userId, Instance $instance): int;

    /**
     * Publishes an alert to the given user.
     *
     * @param int      $userId
     * @param Instance $instance
     * @return int the number of recipients successfully alerted
     */
    public function alertUser(int $userId, Instance $instance): int;
}
