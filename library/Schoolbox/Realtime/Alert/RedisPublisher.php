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

class RedisPublisher implements IPublisher
{
    /** @var ClientInterface */
    private $redis;

    /** @var string */
    private $instanceId;


    /**
     * @param ClientInterface $redis
     * @param string          $instanceId
     */
    public function __construct(ClientInterface $redis, $instanceId)
    {
        $this->redis      = $redis;
        $this->instanceId = $instanceId;
    }


    /**
     * @inheritdoc
     */
    public function alertUser($userId, Instance $instance)
    {
        $topic = "msg:{$this->instanceId}:{$userId}:alert";

        return (int)$this->redis->publish($topic, "$instance");
    }
}
