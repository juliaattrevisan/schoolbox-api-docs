<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Alert;

use Alaress\Async\Notification\Instance\Instance;
use Predis\ClientInterface;
use Schoolbox\Realtime\Pipe\PipeManager;

/**
 * Redis-based message publisher, used to publish messages directly to a
 * user's websocket without going through the notification subsystem.
 */
class RedisPublisher implements IPublisher
{
    /**
     * The Redis interface to publish to.
     */
    private ClientInterface $redis;

    /**
     * The instance ID: used to determine a queue within Redis to publish to.
     */
    private string $instanceId;

    /**
     * @param ClientInterface $redis
     * @param string          $instanceId
     */
    public function __construct(ClientInterface $redis, string $instanceId)
    {
        $this->redis      = $redis;
        $this->instanceId = $instanceId;
    }

    /**
     * @inheritdoc
     */
    public function pushUser(int $userId, Instance $instance): int
    {
        return $this->publish($userId, $instance, PipeManager::PIPE_METHOD_PUSH);
    }

    /**
     * @inheritdoc
     */
    public function alertUser(int $userId, Instance $instance): int
    {
        return $this->publish($userId, $instance, PipeManager::PIPE_METHOD_ALERT);
    }

    /**
     * Helper method for publishing a message to a user.
     *
     * @param int      $userId
     * @param Instance $instance
     * @param string   $pipeMethod
     * @return int
     */
    private function publish(int $userId, Instance $instance, string $pipeMethod): int
    {
        $topic = sprintf("msg:%s:%d:%s", $this->instanceId, $userId, $pipeMethod);
        return $this->redis->publish($topic, "$instance");
    }
}
