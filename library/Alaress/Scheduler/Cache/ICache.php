<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Scheduler\Cache;

use Alaress\Scheduler\Trigger\TriggerInterface;
use Schoolbox\Notif\MQ\Connector;

/**
 * Interface for implementations of trigger caches.
 */
interface ICache
{
    /**
     * Restore triggers from the cache into a message queue.
     *
     * When future triggers are pulled from the message queue, they are stored
     * in the cache using `storeCache`: this pushes them back to the queue so
     * they may be re-processed.
     *
     * @param Connector $mq        The message queue connection.
     * @param string    $queueName The name of the queue to restore to.
     * @return bool
     */
    public function restoreSchedule(Connector $mq, string $queueName): bool;

    /**
     * Store a trigger in the cache.
     *
     * @param TriggerInterface $trigger The trigger to cache.
     * @return bool
     */
    public function storeCache(TriggerInterface $trigger): bool;

    /**
     * Remove a trigger from the cache.
     *
     * @param string $itemKey
     * @return bool
     */
    public function removeCache(string $itemKey): bool;
}
