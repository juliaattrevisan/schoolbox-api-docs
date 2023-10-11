<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Subscription;

use Alaress\Async\Notification\Instance\Instance;

/**
 *  Interface for subscriptions to a queue.
 */
interface ISubscription
{
    /**
     * Unsubscribe from a queue.
     *
     * @return void
     */
    public function unsubscribe(): void;

    /**
     * Fetch the route key for this subscription: required in order to
     * subscribe to and unsubscribe from a queue.
     *
     * @return string
     */
    public function getRouteKey(): string;

    /**
     * Acknowledge a message with a given ID.
     *
     * @param string $messageId
     * @return bool Was the message actually acknowledged?
     */
    public function acknowledgeMessage(string $messageId): bool;

    /**
     * Returns the messageIds of the messages that are on this unread list
     *
     * @return array<Instance>
     */
    public function getUnread(): array;
}
