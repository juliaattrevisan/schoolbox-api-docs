<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing;

use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Stomp\IClient;
use Predis\ClientInterface;

class Broker extends \Alaress\Async\Broker\StompRedis\Broker
{
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
        parent::__construct("$instanceId.route", $amqp, $redis, $stomp);
        $this->instanceId = $instanceId;
    }


    /**
     * @param Instance $instance
     * @param string   $hash
     * @return string
     */
    protected function getStorageKey($instance, $hash)
    {
        return "msg:{$this->instanceId}:m:{$instance->getBaseHash()}:{$instance->getParamHash()}";
    }


    /**
     * @return string
     */
    protected function getRoutingKey()
    {
        return $this->getQueue();
    }


    /**
     * @return string
     */
    protected function getExchangeName()
    {
        return '';
    }
}
