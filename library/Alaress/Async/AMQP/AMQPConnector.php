<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\AMQP;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;

/**
 * Base definition for AMQP queues.
 */
abstract class AMQPConnector implements AMQPInterface
{
    /**
     * Are exchange and queue connections passive?
     *
     * If true, the connection will create the exchange/queue if it doesn't
     * exist: otherwise it'll raise an error.
     *
     * @see https://www.rabbitmq.com/amqp-0-9-1-reference.html#exchange.declare.passive
     * @see https://www.rabbitmq.com/amqp-0-9-1-reference.html#queue.declare.passive
     */
    protected bool $passive = false;

    /**
     * Do exchanges and queues survive server restart?
     *
     * @see https://www.rabbitmq.com/amqp-0-9-1-reference.html#exchange.declare.durable
     * @see https://www.rabbitmq.com/amqp-0-9-1-reference.html#queue.declare.durable
     */
    protected bool $durable = true;

    /**
     * Are exchanges and queues deleted once final client disconnects?
     *
     * @see https://www.rabbitmq.com/amqp-0-9-1-reference.html#exchange.declare.auto-delete
     * @see https://www.rabbitmq.com/amqp-0-9-1-reference.html#queue.declare.auto-delete
     */
    protected bool $autoDelete = false;

    /**
     * Are exchanges invisible to publishers
     * (e.g. for use only by other exchanges)?
     *
     * @see https://www.rabbitmq.com/amqp-0-9-1-reference.html#exchange.declare.internal
     */
    protected bool $internal = false;

    /**
     * Are queues exclusive to this connection?
     *
     * @see https://www.rabbitmq.com/amqp-0-9-1-reference.html#queue.declare.exclusive
     */
    protected bool $exclusive = false;


    /**
     * Unused in this implementation
     *
     * @see https://www.rabbitmq.com/amqp-0-9-1-reference.html#domain.no-local
     */
    protected bool $noLocal = false;

    /**
     * Unused in this implementation
     *
     * @see https://www.rabbitmq.com/amqp-0-9-1-reference.html#domain.no-ack
     */
    protected bool $noAck = false;

    /**
     * Unused in this implementation
     *
     * @see https://www.rabbitmq.com/amqp-0-9-1-reference.html#domain.no-wait
     */
    protected bool $noWait = false;

    /**
     * The delivery mode for messages: Non-persistent (1) or persistent (2).
     */
    protected int $persist = 2;

    /**
     * Default expiration for messages, in milliseconds. (Defaults to 2 weeks)
     */
    protected int $expiry = 1209600000;

    /**
     * A list of channels
     *
     * @var AMQPChannel[]
     */
    protected array $channels = [];


    /**
     * Constructor
     *
     * @param array $settings Various connection-related settings:
     *                        * passive
     *                        * durable
     *                        * autoDelete
     *                        * internal
     *                        * exclusive
     *                        * noLocal
     *                        * noAck
     *                        * noWait
     *                        * persist
     *                        * expiry
     */
    public function __construct(array $settings = [])
    {
        foreach ($settings as $property => $value) {
            if (property_exists($this, $property)) {
                $this->$property = $value;
            }
        }
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

        // Recreate the channel if it's there and disconnected.
        // This could happen if the channel was created for a connection which
        // has since been disconnected?
        $channel = $this->channels[$idkey] ?? null;
        if ($channel) {
            $connected = $channel->getConnection() && $channel->getConnection()->isConnected();
            if (!$connected) {
                try {
                    $channel->close();
                } catch (\Exception) {
                    // ignore errors raised when attempting to close the channel
                }
                unset($channel);
                $channel = null;
            }
        }

        if (!$channel instanceof AMQPChannel) {
            $channel = $this->getConnection()->channel($id);

            // automatically declares the primary exchange
            $channel->exchange_declare(
                $this->getPrimaryExchange()->getName(),
                $this->getPrimaryExchange()->getType(),
                $this->passive,
                $this->durable,
                $this->autoDelete,
                $this->internal
            );
            $this->channels[$idkey] = $channel;
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
            [$queueName, $messageCount, $consumerCount] = $this->getChannel($channelId)->queue_declare(
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
        $this->getChannel($channelId)->basic_ack($message->getDeliveryTag());
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
        $this->getChannel($channelId)->basic_reject($message->getDeliveryTag(), $requeue);
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
     * Connects to an instance of AMQPConnection, and returns that connection.
     *
     * @return AbstractConnection
     */
    abstract protected function getConnection(): AbstractConnection;


    /**
     * Closes the existing connection.
     *
     * @return $this
     */
    abstract public function closeConnection(): self;
}
