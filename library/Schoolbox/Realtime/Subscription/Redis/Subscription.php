<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Subscription\Redis;

use Psr\Log\LoggerInterface;
use Schoolbox\Realtime\Subscription\ISubscription;
use Alaress\Redis\Async;
use Predis;

/**
 * A Redis-backed subscription.
 *
 * Requires the following objects in Redis:
 * * a subscription to a queue
 * * a set, which contains one or more keys to actual messages
 * * actual messages
 */
class Subscription implements ISubscription
{
    public const PATTERN_SUBSCRIPTION = 'msg:%s:%d:*';
    public const PATTERN_SET          = 'msg:%s:q:%d:unread';
    public const PATTERN_MESSAGE      = 'msg:%s:m:%s:*';

    /**
     * Constructor
     *
     * @param string                 $instanceId      The instance ID within which the user exists.
     * @param int                    $userId          The ID of the subscription's user.
     * @param Predis\ClientInterface $syncClient      Synchronous Redis client: used for acknowledging messages.
     * @param Async\Client           $asyncClient     Asynchronous Redis client: used for subscribing to a queue.
     * @param Async\EventListener    $listener        The event listener for queue subscriptions.
     * @param UnreadRetriever        $unreadRetriever Fetches unread messages for this subscription.
     * @param LoggerInterface        $log             Logger
     * @throws Predis\ClientException
     */
    public function __construct(
        private string $instanceId,
        private int $userId,
        private Predis\ClientInterface $syncClient,
        private Async\Client $asyncClient,
        Async\EventListener $listener,
        private UnreadRetriever $unreadRetriever,
        private LoggerInterface $log,
    ) {
        $this->log->debug("Subscribing to queue for instance:user {$this->instanceId}:{$this->userId}");
        $this->asyncClient->psubscribe($this->getRouteKey(), $listener);
    }

    /**
     * Destructor:
     * * unsubscribe from queue
     * * release reference to Redis clients, so they may be garbage-collected
     */
    public function __destruct()
    {
        try {
            $this->unsubscribe();
        } catch (\Exception $e) {
            // destructors must NOT throw exceptions
        }

        // unload object refs
        unset($this->asyncClient, $this->syncClient, $this->log);
    }

    /**
     * @inheritdoc
     *
     * @throws Predis\ClientException if for some reason unsubscribe failed?
     */
    public function unsubscribe(): void
    {
        $this->log->debug("Unsubscribing from queue for instance:user {$this->instanceId}:{$this->userId}");
        $this->asyncClient->punsubscribe($this->getRouteKey());
    }

    /**
     * @inheritdoc
     */
    public function getRouteKey(): string
    {
        return sprintf(static::PATTERN_SUBSCRIPTION, $this->instanceId, $this->userId);
    }

    /**
     * Retrieve the set identifier for this subscription.
     *
     * @return string
     */
    private function getSetIdentifier(): string
    {
        return sprintf(static::PATTERN_SET, $this->instanceId, $this->userId);
    }

    /**
     * Retrieve the value in the set identified by `getSetIdentifier` for this
     * message, if it is unread.
     *
     * @param string $baseHash The message's base hash: multiple instances of
     *                         this message may exist in the set.
     * @return string
     */
    private function getMessageScan(string $baseHash): string
    {
        return sprintf(static::PATTERN_MESSAGE, $this->instanceId, $baseHash);
    }

    /**
     * @inheritdoc
     */
    public function acknowledgeMessage(string $messageId): bool
    {
        $cursor  = 0;
        $removed = 0;

        // Find each instance of the base message within the set, and
        // acknowledge it as read by removing it from the set.
        do {
            /** @var array $messageIds */
            [$cursor, $messageIds] = $this->syncClient->sscan(
                $this->getSetIdentifier(),
                $cursor,
                ['match' => $this->getMessageScan($messageId)],
            );

            foreach ($messageIds as $id) {
                $removed += $this->syncClient->srem($this->getSetIdentifier(), $id);
            }
        } while ($cursor);

        return $removed > 0;
    }

    /**
     * @inheritdoc
     */
    public function getUnread(): array
    {
        return $this->unreadRetriever->getUnread($this->instanceId, $this->userId);
    }
}
