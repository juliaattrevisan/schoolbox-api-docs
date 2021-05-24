<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Subscription;

use Alaress\Async\Notification\Instance\Instance;

interface ISubscription
{

    /**
     * @return void
     */
    public function unsubscribe();

    /**
     * @return string
     */
    public function getRouteKey();

    /**
     * @param string $messageId
     * @return bool
     */
    public function acknowledgeMessage($messageId);

    /**
     * Returns the messageIds of the messages that are on this unread list
     *
     * @return Instance[]
     */
    public function getUnread();
}
