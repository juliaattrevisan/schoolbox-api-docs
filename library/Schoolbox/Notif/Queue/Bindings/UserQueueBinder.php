<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue\Bindings;


use Alaress\Async\AMQP\AMQPExchange;
use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Async\AMQP\Bunny\ControlInterface;
use Alaress\Async\Queue\AMQPQueue;
use OutOfBoundsException;
use RuntimeException;

class UserQueueBinder
{

    /** @var int */
    protected $userId = 0;

    /** @var array */
    protected $bindings = array();

    /** @var array */
    protected $existingBindings = null;


    /**
     * @param array $bindings
     * @return self
     */
    public function setBindings(array $bindings)
    {
        $this->bindings = array();

        $this->userId = $bindings['user']['id'];

        unset($bindings['user']);

        // the user's personal routing keys
        $this->bindings = array(
            "*.notify.user.$this->userId.#",
            "*.digest.user.$this->userId.#",
            "*.instant.user.$this->userId.#"
        );

        // all remaining bindings specified
        foreach ($bindings as $type => $binds) {
            foreach ($binds as $id) {
                $this->bindings[] = "*.notify.$type.$id";
            }
        }

        return $this;
    }


    /**
     * @return array
     */
    public function getBindings()
    {
        return $this->bindings;
    }


    /**
     * @param AMQPInterface    $conn
     * @param AMQPExchange     $exchange
     * @param AMQPQueue[]      $queues
     * @throws OutOfBoundsException
     * @return self
     */
    public function bindToAMQP(AMQPInterface $conn, AMQPExchange $exchange, array $queues)
    {
        if (empty($this->bindings) || !$this->userId) {
            throw new OutOfBoundsException("Must have valid bindings");
        }

        $exchangeName = $exchange->getName();

        // exchange things
        $conn->declareExchange($exchangeName);

        foreach ($this->bindings as $key) {
            $conn->bindExchange($exchangeName, $key);
        }

        // queue things
        foreach ($queues as $q) {
            $queueName = $q->getName();
            $routeKey  = $q->getRoutingKey();
            $conn->declareQueue($queueName);
            $conn->bindQueue($queueName, $routeKey, $exchangeName);
        }

        return $this;
    }


    /**
     * Destroy all routing bindings for the given user.
     * Returns the number of bindings deleted, or -1 on error.
     *
     * @param AMQPInterface    $conn
     * @param ControlInterface $rabbitController
     * @param int              $userId
     * @return int
     */
    public function destroyUserBindings(AMQPInterface $conn, ControlInterface $rabbitController, $userId)
    {

        if (!$this->existingBindings) {
            $this->cacheExistingBindings($conn, $rabbitController);
        }

        if (!isset($this->existingBindings[$userId])) {
            return 0;
        }

        $count = 0;

        $userExchange = $conn->getSecondaryExchange($userId);

        foreach ($this->existingBindings[$userId] as $routingKey) {
            $conn->unbindExchange($userExchange->getName(), $routingKey);
            ++$count;
        }


        return $count;
    }


    /**
     * Retrieve all existing bindings from RabbitMQ and cache into an array, indexed by username
     *
     * @param AMQPInterface    $conn
     * @param ControlInterface $bunny
     * @return int
     */
    protected function cacheExistingBindings(AMQPInterface $conn, ControlInterface $bunny)
    {
        $this->existingBindings = array();

        try {
            $bindings = $bunny->listBindings(array('destination_name', 'routing_key'));
        } catch (RuntimeException $e) {
            return -1;
        }

        if (!count($bindings)) {
            return 0;
        }

        $count  = 0;
        $prefix = $conn->getSecondaryExchange('')
                       ->getName();

        foreach ($bindings as $binding) {
            // only parse line if this is a user exchange
            if (substr($binding['destination_name'], 0, strlen($prefix)) != $prefix) {
                continue;
            }

            $username = substr($binding['destination_name'], strlen($prefix));

            $this->existingBindings[$username][] = $binding['routing_key'];

            ++$count;
        }

        return $count;
    }


    /**
     * Drop the cache of existing bindings
     */
    public function dropCache()
    {
        $this->existingBindings = null;
    }

}