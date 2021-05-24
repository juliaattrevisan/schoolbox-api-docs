<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Subscription\Redis;

use Alaress\Async\Notification\Instance\Instance;
use InvalidArgumentException;
use Schoolbox\Realtime\Subscription\ISubscription;
use Alaress\Redis\Async;
use Predis;

class Subscription implements ISubscription
{
    public const PATTERN_SUBSCRIPTION = 'msg:%s:%d:*';
    public const PATTERN_SET          = 'msg:%s:q:%d:unread';
    public const PATTERN_MESSAGE      = 'msg:%s:m:%s:*';


    /** @var string */
    private $instanceId;

    /** @var int */
    private $userId;

    /** @var Async\Client */
    private $asyncClient;

    /** @var Predis\ClientInterface */
    private $syncClient;

    /** @var UnreadRetriever */
    private $unreadRetriever;

    /**
     * Constructor
     *
     * @param string                 $instanceId
     * @param int                    $userId
     * @param Predis\ClientInterface $syncClient
     * @param Async\Client           $asyncClient
     * @param Async\EventListener    $listener
     * @param UnreadRetriever        $unreadRetriever
     * @throws Predis\ClientException
     */
    public function __construct(
        $instanceId,
        $userId,
        Predis\ClientInterface $syncClient,
        Async\Client $asyncClient,
        Async\EventListener $listener,
        UnreadRetriever $unreadRetriever
    ) {
        $this->instanceId      = $instanceId;
        $this->userId          = $userId;
        $this->asyncClient     = $asyncClient;
        $this->syncClient      = $syncClient;
        $this->unreadRetriever = $unreadRetriever;

        $this->asyncClient->psubscribe($this->getRouteKey(), $listener);
    }


    /**
     * Clean yourself up
     */
    public function __destruct()
    {
        try {
            $this->unsubscribe();
        } catch (\Exception $e) {
            // destructors must NOT throw exceptions
        }

        // unload object refs
        unset($this->asyncClient, $this->syncClient);
    }


    /**
     * @throws Predis\ClientException
     * @return void
     */
    public function unsubscribe()
    {
        if ($this->asyncClient instanceof Async\Client) {
            $this->asyncClient->unsubscribe($this->getRouteKey());
            unset($this->asyncClient);
        }
    }


    /**
     * @return string
     */
    public function getRouteKey()
    {
        return sprintf(static::PATTERN_SUBSCRIPTION, $this->instanceId, $this->userId);
    }


    /**
     * @return string
     */
    private function getSetIdentifier()
    {
        return sprintf(static::PATTERN_SET, $this->instanceId, $this->userId);
    }

    /**
     * @param string $baseHash
     * @return string
     */
    private function getMessageScan($baseHash)
    {
        return sprintf(static::PATTERN_MESSAGE, $this->instanceId, $baseHash);
    }

    /**
     * @param string $messageId
     * @return bool
     */
    public function acknowledgeMessage($messageId)
    {
        $cursor  = 0;
        $removed = 0;

        do {

            /** @var array $messageIds */
            list($cursor, $messageIds) = $this->syncClient->sscan($this->getSetIdentifier(), $cursor, [ 'match' => $this->getMessageScan($messageId) ]);

            foreach ($messageIds as $id) {
                $removed += $this->syncClient->sRem($this->getSetIdentifier(), $id);
            }
        } while ($cursor);

        return $removed > 0;
    }


    /**
     * Returns the messages contained within this subscription
     *
     * @throws InvalidArgumentException
     * @return Instance[]
     */
    public function getUnread()
    {
        return $this->unreadRetriever->getUnread($this->instanceId, $this->userId);
    }
}
