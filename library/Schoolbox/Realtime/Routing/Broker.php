<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Routing;

use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Async\Notification\Instance\Instance;
use Predis\ClientInterface;
use React\Stomp\Client;


class Broker extends \Alaress\Async\Broker\StompRedis\Broker
{
    /** @var string */
    private $instanceId;


    /**
     * @param string          $instanceId
     * @param AMQPInterface   $amqp
     * @param ClientInterface $redis
     * @param Client          $stomp
     */
    public function __construct($instanceId, AMQPInterface $amqp, ClientInterface $redis, Client $stomp = null)
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