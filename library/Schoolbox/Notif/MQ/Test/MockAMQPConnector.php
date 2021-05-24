<?php

 /**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Notif\MQ\Test;

use Alaress\Async\AMQP\AMQPExchange;
use Alaress\Async\AMQP\AMQPInterface;
use PhpAmqpLib\Message\AMQPMessage;
use SplQueue;

class MockAMQPConnector implements AMQPInterface
{

    /** @var SplQueue */
    protected $splQueue;


    /**
     * Pre-populate with AMQPMessages
     *
     * @param AMQPMessage[] $settings
     */
    public function __construct($settings = array())
    {
        $this->splQueue = new SplQueue();
        foreach ($settings as $msg) {
            if ($msg instanceof AMQPMessage) {
                $this->splQueue->enqueue($msg);
            }
        }
    }


    /**
     * Declare an exchange
     *
     * @param AMQPExchange $exchange
     * @param string $channelId
     * @return $this
     */
    public function declareExchange($exchange, $channelId = null)
    {
        return $this;
    }


    /**
     * Declare a queue for use (which will create it if it does not already exist).
     *
     * @param string $name
     * @param string $channelId
     * @return $this
     */
    public function declareQueue($name, $channelId = null)
    {
        return $this;
    }


    /**
     * Bind a routing key to a particular exchange.
     *
     * @param string $exchangeName
     * @param string $routingKey
     * @param string $channelId
     * @return $this
     */
    public function bindExchange($exchangeName, $routingKey, $channelId = null)
    {
        return $this;
    }


    /**
     * Bind a routing key to a particular queue.
     *
     * @param string $queueName
     * @param string $routingKey
     * @param string $exchangeName
     * @param string $channelId
     * @return $this
     */
    public function bindQueue($queueName, $routingKey, $exchangeName = '', $channelId = null)
    {
        return $this;
    }


    /**
     * Unbind a routing key from a particular queue.
     *
     * @param string $queueName
     * @param string $routingKey
     * @param string $exchangeName
     * @param string $channelId
     * @return $this
     */
    public function unbindQueue($queueName, $routingKey, $exchangeName = '', $channelId = null)
    {
        return $this;
    }


    /**
     * Unbind a routing key from a particular exchange.
     *
     * @param string $exchangeName
     * @param string $routingKey
     * @param string $channelId
     * @return $this
     */
    public function unbindExchange($exchangeName, $routingKey, $channelId = null)
    {
        return $this;
    }


    /**
     * Mark the message identified by the given delivery tag as ACK.
     *
     * @param AMQPMessage $message
     * @param string $channelId
     * @return $this
     */
    public function acceptMessage($message, $channelId = null)
    {
        return $this;
    }


    /**
     * Mark the given array of messages as accepted.
     *
     * @param AMQPMessage[] $messages
     * @param string $channelId
     * @return $this
     */
    public function acceptMessages($messages, $channelId = null)
    {
        return $this;
    }


    /**
     * Mark the given array of messages as rejected.
     *
     * @param AMQPMessage[] $messages
     * @param bool $requeue
     * @param string $channelId
     * @return $this
     */
    public function rejectMessages($messages, $requeue = true, $channelId = null)
    {
        foreach ($messages as $m) {
            $this->rejectMessage($m, $requeue);
        }
        return $this;
    }


    /**
     * Mark the message identified by the given delivery tag as rejected.
     *
     * @param AMQPMessage $message
     * @param bool $requeue
     * @param string $channelId
     * @return $this
     */
    public function rejectMessage($message, $requeue = true, $channelId = null)
    {
        if ($requeue) {
            $this->splQueue->enqueue($message);
        }
        return $this;
    }


    /**
     * Return the top message from a queue. Messages are set to unacknowledged, and subsequently need
     * to be either accepted or rejected.
     *
     * @param string $queueName
     * @param string $channelId
     * @return AMQPMessage
     */
    public function readFromQueue($queueName, $channelId = null)
    {
        if ($this->splQueue->count()) {
            return $this->splQueue->dequeue();
        }
        return null;
    }


    /**
     * Publish a message to a particular routing key.
     *
     * @param AMQPMessage $message
     * @param string $routingKey
     * @param array  $properties
     * @param string $channelId
     * @return $this
     */
    public function publish($message, $routingKey, $properties = array(), $channelId = null)
    {
        return $this;
    }


    /**
     * Delete the given queue
     *
     * @param string $queueName
     * @param string $channelId
     * @return $this
     */
    public function deleteQueue($queueName, $channelId = null)
    {
        return $this;
    }


    /**
     * Delete the given exchange
     *
     * @param string $exchangeName
     * @param string $channelId
     * @return $this
     */
    public function deleteExchange($exchangeName, $channelId = null)
    {
        return $this;
    }


    /**
     * @return AMQPExchange
     */
    public function getPrimaryExchange()
    {
        return new AMQPExchange('mock', 'mock');
    }
}
