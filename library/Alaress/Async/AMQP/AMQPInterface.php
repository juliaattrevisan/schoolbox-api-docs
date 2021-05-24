<?php

 /**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\AMQP;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

interface AMQPInterface
{

    /**
     * Accept optional settings for construction
     *
     * @param array $settings
     */
    public function __construct($settings = array());


    /**
     * Declare an exchange
     *
     * @param AMQPExchange $exchange
     * @param string $channelId
     * @return self
     */
    public function declareExchange($exchange, $channelId = null);


    /**
     * Declare a queue for use (which will create it if it does not already exist).
     *
     * @param string $name
     * @param string $channelId
     * @return self
     */
    public function declareQueue($name, $channelId = null);


    /**
     * Bind a routing key to a particular exchange.
     *
     * @param string $exchangeName
     * @param string $routingKey
     * @param string $channelId
     * @return self
     */
    public function bindExchange($exchangeName, $routingKey, $channelId = null);


    /**
     * Bind a routing key to a particular queue.
     *
     * @param string $queueName
     * @param string $routingKey
     * @param string $exchangeName
     * @param string $channelId
     * @return AMQPChannel
     */
    public function bindQueue($queueName, $routingKey, $exchangeName = '', $channelId = null);


    /**
     * Unbind a routing key from a particular queue.
     *
     * @param string $queueName
     * @param string $routingKey
     * @param string $exchangeName
     * @param string $channelId
     * @return AMQPChannel
     */
    public function unbindQueue($queueName, $routingKey, $exchangeName = '', $channelId = null);


    /**
     * Unbind a routing key from a particular exchange.
     *
     * @param string $exchangeName
     * @param string $routingKey
     * @param string $channelId
     * @return AMQPChannel
     */
    public function unbindExchange($exchangeName, $routingKey, $channelId = null);


    /**
     * Mark the message identified by the given delivery tag as ACK.
     *
     * @param AMQPMessage $message
     * @param string $channelId
     * @return self
     */
    public function acceptMessage($message, $channelId = null);


    /**
     * Mark the given array of messages as accepted.
     *
     * @param array $messages
     * @param string $channelId
     * @return self
     */
    public function acceptMessages($messages, $channelId = null);


    /**
     * Mark the given array of messages as rejected.
     *
     * @param array $messages
     * @param bool $requeue
     * @param string $channelId
     * @return self
     */
    public function rejectMessages($messages, $requeue = true, $channelId = null);


    /**
     * Mark the message identified by the given delivery tag as rejected.
     *
     * @param AMQPMessage $message
     * @param bool $requeue
     * @param string $channelId
     * @return self
     */
    public function rejectMessage($message, $requeue = true, $channelId = null);


    /**
     * Return the top message from a queue. Messages are set to unacknowledged, and subsequently need
     * to be either accepted or rejected.
     *
     * @param string $queueName
     * @param string $channelId
     * @return mixed
     */
    public function readFromQueue($queueName, $channelId = null);


    /**
     * Publish a message to a particular routing key.
     *
     * @param AMQPMessage|string $message
     * @param string $routingKey
     * @param array  $properties
     * @param string $channelId
     * @return self
     */
    public function publish($message, $routingKey, $properties = array(), $channelId = null);


    /**
     * Delete the given queue
     *
     * @param string $queueName
     * @param string $channelId
     * @return self
     */
    public function deleteQueue($queueName, $channelId = null);


    /**
     * Delete the given exchange
     *
     * @param string $exchangeName
     * @param string $channelId
     * @return self
     */
    public function deleteExchange($exchangeName, $channelId = null);


    /**
     * @return AMQPExchange
     */
    public function getPrimaryExchange();
}
