<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Subscription\Redis;

use Alaress\Async\Notification\Instance\Instance;
use InvalidArgumentException;
use Redis;
use Schoolbox\Realtime\Subscription\ISubscription;
use Alaress\Redis\Async;
use Predis;

class Subscription implements ISubscription
{
    const PATTERN_SUBSCRIPTION = 'msg:%s:%d:*';
    const PATTERN_SET          = 'msg:%s:q:%d:unread';
    const PATTERN_MESSAGE      = 'msg:%s:m:%s:*';


    /** @var string */
    private $instanceId;

    /** @var int */
    private $userId;

    /** @var Async\Client */
    private $asyncClient;

    /** @var Redis|Predis\ClientInterface */
    private $syncClient;


    /**
     * @param string                 $instanceId
     * @param int                    $userId
     * @param Async\Client           $asyncClient
     * @param Predis\ClientInterface $syncClient
     * @param Async\EventListener    $listener
     * @throws Predis\ClientException
     */
    public function __construct($instanceId, $userId, Predis\ClientInterface $syncClient, Async\Client $asyncClient, Async\EventListener $listener)
    {
        $this->instanceId  = $instanceId;
        $this->userId      = $userId;
        $this->asyncClient = $asyncClient;
        $this->syncClient  = $syncClient;

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
            unset ($this->asyncClient);
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
            list($cursor, $messageIds) = $this->syncClient->sscan($this->getSetIdentifier(), $cursor, [ 'match' => $this->getMessageScan($messageId) ]);

            /** @var array $messageIds */
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
        return array_map(function ($messageKey) {
            $json = json_decode($this->syncClient->get($messageKey), true);
            return Instance::jsonUnserialize($json['message']);
        }, $this->syncClient->sMembers($this->getSetIdentifier()));
    }

}