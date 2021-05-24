<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule;

use Alaress\Async\Broker\StompRedis;
use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Async\Stomp\IClient;
use Predis\ClientInterface;

class Broker extends StompRedis\Broker
{
    const QUEUE = 'sb.schedule';

    /** @var string */
    private $instanceId;


    /**
     * @param string          $instanceId
     * @param AMQPInterface   $amqp
     * @param ClientInterface $redis
     * @param IClient         $stomp
     */
    public function __construct($instanceId, AMQPInterface $amqp, ClientInterface $redis, IClient $stomp = null)
    {
        parent::__construct(self::QUEUE, $amqp, $redis, $stomp);
        $this->instanceId = $instanceId;
    }


    /**
     * @param mixed  $serializable
     * @param string $hash
     * @return string
     */
    protected function getStorageKey($serializable, $hash)
    {
        return "msg:{$this->instanceId}:s:$hash";
    }


    /**
     * @return string
     */
    protected function getRoutingKey()
    {
        return $this->getQueue(); // the routing key is simply the queue's name
    }


    /**
     * @return string
     */
    protected function getExchangeName()
    {
        return ''; // we want to use AMQP default exchange
    }
}
