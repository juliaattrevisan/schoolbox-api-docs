<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\AMQP;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;

abstract class AMQPConnector implements AMQPInterface
{

    // default connection vars

    /** @var bool */
    protected $passive    = false;
    /** @var bool */
    protected $durable    = true;       // exchanges and queues survive server restart
    /** @var bool */
    protected $autoDelete = false;      // exchanges and queues are not deleted when final client disconnects
    /** @var bool */
    protected $internal   = false;
    /** @var bool */
    protected $exclusive  = false;      // more than one connection allowed per queue

    /** @var bool */
    protected $noLocal    = false;
    /** @var bool */
    protected $noAck      = false;
    /** @var bool */
    protected $noWait     = false;

    /** @var int */
    protected $persist    = 2;          // save to disk
    /** @var int */
    protected $expiry     = 1209600000; // 1000 * 60 * 60 * 24 * 14 = 2 weeks in millis

    /**
     * @var AMQPStreamConnection
     */
    protected $conn;

    /** @var AMQPChannel[] */
    protected $channels;


    /**
     * @param array $settings
     */
    public function __construct($settings = [])
    {
        foreach ($settings as $property => $value) {
            if (property_exists($this, $property)) {
                $this->$property = $value;
            }
        }

        // override any naughty settings
        $this->conn = null;
        $this->channels = [];
    }


    /**
     * @return AMQPStreamConnection
     */
    public function getConnection()
    {
        if (!$this->conn instanceof AMQPStreamConnection) {
            $this->connect();
        }
        return $this->conn;
    }


    /**
     * Closes the existing connection
     * @return $this
     */
    public function closeConnection()
    {
        $this->conn->close();
        $this->conn = null;
        return $this;
    }


    /**
     * Retrieves the channel of this connector, optionally by an ID.
     *
     * @param string $id
     * @return AMQPChannel
     */
    public function getChannel($id = null)
    {
        // can't use null as a key
        $idkey = $id ?: '_';

        if (!(isset($this->channels[$idkey]) && $this->channels[$idkey] instanceof AMQPChannel)) {
            $this->channels[$idkey] = $this->getConnection()->channel($id);

            // automatically declares the primary exchange
            $this->channels[$idkey]->exchange_declare(
                $this->getPrimaryExchange()->getName(),
                $this->getPrimaryExchange()->getType(),
                $this->passive,
                $this->durable,
                $this->autoDelete,
                $this->internal
            );
        }
        return $this->channels[$idkey];
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
        $this->getChannel($channelId)->exchange_declare(
            $exchange->getName(),
            $exchange->getType(),
            $this->passive,
            $this->durable,
            $this->autoDelete,
            $this->internal
        );
        return $this;
    }


    /**
     * Declares a queue for use (which will create it if it does not already exist).
     *
     * @param string $name
     * @param string $channelId
     * @throws RuntimeException
     * @return $this
     */
    public function declareQueue($name, $channelId = null)
    {
        try {
            list($queueName, $messageCount, $consumerCount) = $this->getChannel($channelId)->queue_declare(
                $name,
                $this->passive,
                $this->durable,
                $this->exclusive,
                $this->autoDelete
            );
            if (!$queueName) {
                throw new RuntimeException("Unable to declare queue '$name'.");
            }
            return $this;
        } catch (AMQPProtocolChannelException $e) {
            throw new RuntimeException("Unable to declare queue '$name'.", 0, $e);
        }
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
        $this->getChannel($channelId)->exchange_bind($exchangeName, $this->getPrimaryExchange()->getName(), $routingKey);
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
        $exchangeName = $exchangeName ?: $this->getPrimaryExchange()->getName();
        $this->getChannel($channelId)->queue_bind($queueName, $exchangeName, $routingKey);
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
        $exchangeName = $exchangeName ?: $this->getPrimaryExchange()->getName();
        $this->getChannel($channelId)->queue_unbind($queueName, $exchangeName, $routingKey);
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
        $this->getChannel($channelId)->exchange_unbind($exchangeName, $this->getPrimaryExchange()->getName(), $routingKey);
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
        $this->getChannel($channelId)->basic_ack($message->delivery_info['delivery_tag']);
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
        foreach ($messages as $m) {
            $this->acceptMessage($m, $channelId);
        }
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
            $this->rejectMessage($m, $requeue, $channelId);
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
        $this->getChannel($channelId)->basic_reject($message->delivery_info['delivery_tag'], $requeue);
        return $this;
    }


    /**
     * Internal function for returning the top message from a queue. Messages are set to unacknowledged.
     *
     * @param string $queueName
     * @param string $channelId
     * @return AMQPMessage
     */
    public function readFromQueue($queueName, $channelId = null)
    {
        return $this->getChannel($channelId)->basic_get($queueName, false);
    }


    /**
     * Publish a message to a particular routing key.
     *
     * @param AMQPMessage|string $message
     * @param string             $routingKey
     * @param array              $properties
     * @param string             $channelId
     * @return $this
     */
    public function publish($message, $routingKey, $properties = [], $channelId = null)
    {
        $exchange = isset($properties['exchange'])
            ? $properties['exchange']
            : $this->getPrimaryExchange()->getName();

        unset($properties['exchange']);

        // wrap message in object if it's not already
        if (!$message instanceof AMQPMessage) {
            $properties = [
                'message_id'    => $this->hashMessage($message, $channelId),
                'delivery_mode' => $this->persist,
                'timestamp'     => isset($properties['timestamp']) ? $properties['timestamp'] : time(),
                'expiration'    => isset($properties['expiration']) ? $properties['expiration'] : $this->expiry
            ];
            $message = new AMQPMessage($message, $properties);
        }
        $this->getChannel($channelId)->basic_publish($message, $exchange, $routingKey);

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
        $this->getChannel($channelId)->queue_delete($queueName);
        return $this;
    }


    /**
     * @param string $exchangeName
     * @param string $channelId
     * @return $this
     */
    public function deleteExchange($exchangeName, $channelId = null)
    {
        $this->getChannel($channelId)->exchange_delete($exchangeName);
        return $this;
    }


    /**
     * Returns the name of the primary exchange of this connection.
     *
     * @return AMQPExchange
     */
    abstract public function getPrimaryExchange();


    /**
     * @param string $message
     * @param string $channelId
     * @return string
     */
    abstract protected function hashMessage($message, $channelId);

    /**
     * Sets $this->conn to an instance of AMQPConnection.
     *
     * @return void
     */
    abstract protected function connect();
}
