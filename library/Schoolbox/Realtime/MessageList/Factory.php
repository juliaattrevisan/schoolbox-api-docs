<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Queue\QueueInterface;
use Alaress\Redis;
use Predis\ClientInterface;
use Serializable;


class Factory implements IFactory, Serializable
{

    /** @var string */
    private $instanceId;

    /** @var ClientInterface */
    private $redis;


    /**
     * @param string          $instanceId
     * @param ClientInterface $redis
     */
    public function __construct($instanceId, ClientInterface $redis)
    {
        $this->instanceId = $instanceId;
        $this->redis      = $redis;
    }


    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function listed($userId)
    {
        return new Listed($this->redis, $this->instanceId, $userId);
    }


    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function unread($userId)
    {
        return new Unread($this->redis, $this->instanceId, $userId);
    }


    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function digest($userId)
    {
        return new Digest($this->redis, $this->instanceId, $userId);
    }


    /**
     * String representation of object, or null
     *
     * @return string
     */
    public function serialize()
    {
        return $this->instanceId;
    }


    /**
     * Constructs the object from the serial string given
     *
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        $this->instanceId = $serialized;
        $this->redis = Redis::getClientInstance(); // TODO IoC
    }
}