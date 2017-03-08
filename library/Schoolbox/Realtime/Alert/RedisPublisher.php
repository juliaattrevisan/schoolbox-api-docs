<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
        // yes, this is double-encoded.
        $payload = json_encode([
            'message' => json_encode($instance)
        ]);

        return (int)$this->redis->publish($topic, $payload);
    }

}